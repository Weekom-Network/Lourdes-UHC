<?php

declare(strict_types=1);

namespace uhc\border;

use pocketmine\block\BlockIds;
use pocketmine\entity\Living;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\utils\SubChunkIteratorManager;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use uhc\game\Game;
use uhc\game\utils\GameValues;
use uhc\player\disconnect\DisconnectMob;
use uhc\player\GamePlayer;
use uhc\UHCLoader;

/**
 * Class BorderManager
 * @package uhc\border
 */
class BorderManager
{

    /** @var string */
    public const PREFIX = TextFormat::LIGHT_PURPLE . '[+]';

    /** @var int */
    public const WALL_HEIGHT = 4;

    /** @var Game */
    private $game;

    /** @var int */
    private $size;

    /** @var SubChunkIteratorManager */
    private $iterator;

    /** @var int */
    private $borderIndex = 0;

    /** @var int[] */
    private $borders;

    /** @var float|int */
    private $teleportDistance = 1.6;

    /** @var int[] */
    private $passableMaterials = [
        BlockIds::AIR,
        BlockIds::DOUBLE_PLANT,
        BlockIds::LEAVES,
        BlockIds::LEAVES2,
        BlockIds::LILY_PAD,
        BlockIds::LOG,
        BlockIds::LOG2,
        BlockIds::RED_FLOWER,
        BlockIds::SNOW_LAYER,
        BlockIds::TALL_GRASS,
        BlockIds::VINES,
        BlockIds::YELLOW_FLOWER
    ];

    /** @var int[] */
    private $broadcastTimes = [60, 30, 10, 5, 4, 3, 2, 1];

    /** @var bool */
    private $canShrink = true;
    /** @var int */
    private $nextSize = -1;
    /** @var int */
    private $nextTime = -1;

    /**
     * Border constructor.
     * @param Game $game
     */
    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->size = 1000;
        $this->borders = [
            35 => 750,
            45 => 500,
            50 => 250,
            55 => 100,
            60 => 50,
            65 => 25
        ];
        $this->update();
    }

    /**
     * @param int $id
     * @return bool
     */
    public function isPassable(int $id): bool
    {
        return in_array($id, $this->passableMaterials);
    }

    /**
     * @return SubChunkIteratorManager
     */
    public function getIterator(): SubChunkIteratorManager
    {
        return $this->iterator;
    }

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return $this->game;
    }

    /**
     * @return int
     */
    public function getBorderIndex(): int
    {
        return $this->borderIndex;
    }

    /**
     * @return float|int
     */
    public function getTeleportDistance()
    {
        return $this->teleportDistance;
    }

    /**
     * @param Level $level
     */
    public function setIterator(Level $level): void
    {
        $this->iterator = new SubChunkIteratorManager($level, false);
    }

    /**
     * @param int[] $borders
     */
    public function setBorders(array $borders): void
    {
        $this->borders = $borders;
    }

    /**
     * @param int $size
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * The statements in this method are used to update our flags
     * & properties
     */
    public function update(): void
    {
        $this->canShrink = $this->canShrink(false);
        $this->nextSize = $this->getNextBorderSize(false);
        $this->nextTime = $this->getNextBorderTime(false);
    }

    /**
     * @param int $borderIndex
     * @return int
     */
    public function getBorderTime(int $borderIndex): int
    {
        $keys = array_keys($this->borders);
        if (isset($keys[$borderIndex])) {
            return $keys[$borderIndex];
        }
        return -1;
    }

    /**
     * @param bool $fromCache
     * @return int
     */
    public function getNextBorderTime(bool $fromCache = true): int
    {
        if ($fromCache) return $this->nextTime;
        if ($this->canShrink()) {
            return $this->getBorderTime($this->getBorderIndex());
        }
        return -1;
    }

    /**
     * @param bool $fromCache
     * @return int
     */
    public function getNextBorderSize(bool $fromCache = true): int
    {
        if ($fromCache) return $this->nextSize;
        $key = $this->getNextBorderTime();
        if ($key !== -1) {
            return $this->getBorders()[$key] ?? -1;
        }
        return -1;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return int[]
     */
    public function getBorders(): array
    {
        return $this->borders;
    }

    /**
     * @param bool $fromCache
     * @return bool
     */
    public function canShrink(bool $fromCache = true): bool
    {
        if ($fromCache) return $this->canShrink;
        return $this->borderIndex <= (count($this->borders) - 1);
    }

    /**
     * Used to shrink the border & create the bedrock border for it
     */
    public function shrink(): void
    {
        $this->size = $this->borders[array_keys($this->borders)[$this->borderIndex]];
        $this->create();
        $this->borderIndex++;
        $this->update();
    }

    /**
     * Calls made when the border shrinks
     */
    public function executeShrink(): void
    {
        $this->shrink();
        $this->getGame()->getPlugin()->getServer()->broadcastMessage(TextFormat::LIGHT_PURPLE . '[+] The border has been shrank to ' . $this->getSize() . 'x' . $this->getSize());
        $this->teleportPlayers();
    }

    /**
     * @param Living $living
     * @return bool
     */
    public function inBorder(Living $living): bool
    {
        if (($living instanceof GamePlayer && !$living->isOnline()) || !$living->isValid()) {
            return true;
        }
        [$x, $z] = [abs($living->getFloorX()), abs($living->getFloorZ())];
        return $living->getLevel()->getFolderName() == $this->getGame()->getLevel()->getFolderName() && ($x < $this->getSize() && $z < $this->getSize());
    }

    /**
     * @param Living $living
     */
    public function teleport(Living $living): void
    {
        if (($living instanceof GamePlayer && !$living->isSpawned()) || !$living->isValid()) {
            return;
        }
        $outsideX = ($living->getFloorX() < 0 ? $living->getFloorX() <= -$this->getSize() : $living->getFloorX() >= $this->getSize());
        $outsideZ = ($living->getFloorZ() < 0 ? $living->getFloorZ() <= -$this->getSize() : $living->getFloorZ() >= $this->getSize());
        $teleportDistance = $this->getTeleportDistance() > $this->getSize() ? $this->getSize() / 2 : $this->getTeleportDistance();
        $position = $living->asPosition();
        $position->x = $outsideX ? (($living->getFloorX() <=> 0) * ($this->getSize() - $teleportDistance)) : $position->x;
        $position->z = $outsideZ ? (($living->getFloorZ() <=> 0) * ($this->getSize() - $teleportDistance)) : $position->z;
        $position->y = $this->getGame()->getLevel()->getHighestBlockAt((int) $position->getX(), (int) $position->getZ()) + 1;

        if ($position->y <= 1)
            $position->y = 128;
        $living->teleport(Location::fromObject($position, $this->getGame()->getLevel(), $living->getYaw(), $living->getPitch()));
    }

    public function teleportPlayers(): void
    {
        # Teleport entities
        $disconnectPlayers = array_filter($this->getGame()->getDisconnectManager()->getDisconnectMobs(), function (DisconnectMob $disconnectedPlayer): bool {
            return $disconnectedPlayer->getData() != null && !$this->inBorder($disconnectedPlayer);
        });
        foreach ($disconnectPlayers as $disconnectedPlayer)
            $this->teleport($disconnectedPlayer);

        # Teleport players
        $players = array_filter($this->getGame()->getPlugin()->getServer()->getOnlinePlayers(), function (Player $player): bool {
            return $player instanceof GamePlayer && $player->isSpawned() && !$this->inBorder($player);
        });
        foreach ($players as $player)
            $this->teleport($player);
    }

    /**
     * @param Level $level
     * @param int $x1
     * @param int $z1
     * @param int $x2
     * @param int $z2
     */
    protected function reloadChunks(Level $level, int $x1, int $z1, int $x2, int $z2): void
    {
        for ($x = $x1 >> 4; $x <= $x2 >> 4; $x++) {
            for ($z = $z1 >> 4; $z <= $z2 >> 4; $z++) {
                $tiles = $level->getChunkTiles($x, $z);
                $chunk = $level->getChunk($x, $z);
                $level->setChunk($x, $z, $chunk);

                foreach ($tiles as $tile) {
                    $tile->closed = false;
                    $tile->setLevel($level);
                    $level->addTile($tile);
                }


                foreach ($level->getChunkLoaders($x, $z) as $chunkLoader) {
                    if ($chunkLoader instanceof Player) {
                        $pk = LevelChunkPacket::withoutCache($x, $z, $chunk->getSubChunkSendCount() + 4, $chunk->networkSerialize($chunk->networkSerializeTiles()));
                        $chunkLoader->dataPacket($pk);
                    }
                }
            }
        }
    }

    /**
     * @param int $x1
     * @param int $x2
     * @param int $z1
     * @param int $z2
     */
    public function createLayer(int $x1, int $x2, int $z1, int $z2): void
    {
        $minX = min($x1, $x2);
        $maxX = max($x1, $x2);

        $minZ = min($z1, $z2);
        $maxZ = max($z1, $z2);
        $level = $this->getGame()->getLevel();
        $this->getIterator()->currentChunk = $level->getChunk($minX >> 4, $minZ >> 4, true);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($z = $minZ; $z <= $maxZ; $z++) {
                $subX = $x & 0x0f;
                $subZ = $z & 0x0f;
                $y = $this->getIterator()->currentChunk->getHighestBlockAt($subX, $subZ);
                $this->getIterator()->moveTo($x, $y, $z);

                while($this->isPassable($this->getIterator()->currentChunk->getBlockId($subX, $y, $subZ)) && $y > 1) {
                    $y -= 1;
                }
                $this->getIterator()->currentChunk->setBlockId($subX, $y + 1, $subZ, BlockIds::BEDROCK);
            }
        }
        $this->reloadChunks($level, $minX, $minZ, $maxX, $maxZ);
    }

    /**
     * @param int $x1
     * @param int $x2
     * @param int $z1
     * @param int $z2
     */
    public function createWall(int $x1, int $x2, int $z1, int $z2): void
    {
        for ($y = 0; $y < self::WALL_HEIGHT; $y++) {
            UHCLoader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($x1, $x2, $z1, $z2): void {
                $this->createLayer($x1, $x2, $z1, $z2);
            }), self::WALL_HEIGHT * 2);
        }
    }

    public function createFloor(): void
    {
        # Get level
        $level = $this->getGame()->getLevel();

        # Get coords
        $minX = -26;
        $minZ = -26;

        $maxX = 26;
        $maxZ = 26;

        # Set chunk iterator level
        $this->getIterator()->currentChunk = $level->getChunk($minX >> 4, $minZ >> 4, true);

        # Create floor
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($z = $minZ; $z <= $maxZ; $z++) {
                # Get sub coord
                $subX = $x & 0x0f;
                $subZ = $z & 0x0f;
                $y = 150;

                # Move iterator
                $this->getIterator()->moveTo($x, $y, $z);

                # Set block
                $this->getIterator()->currentChunk->setBlockId($subX, $y, $subZ, BlockIds::BEDROCK);
                $this->getIterator()->currentChunk->setBlockId($subX, $y + 1, $subZ, BlockIds::GRASS);
            }
        }

        # Reload chunks
        $this->reloadChunks($level, $minX, $minZ, $maxX, $maxZ);
    }

    public function create(): void
    {
        $this->getGame()->getPlugin()->getScheduler()->scheduleRepeatingTask(new BorderTask($this), 5);
    }

    public function check(): void
    {
        if ($this->canShrink()) {
            $borderTime = $this->getNextBorderTime();
            $borderSize = $this->getNextBorderSize(false);
            $next = ($borderTime * 60) - GameValues::$RUNNING;
            $broadcastMatches = array_filter($this->broadcastTimes, function (int $broadcastTime) use ($next): bool {
                return $next === $broadcastTime;
            });

            if (count($broadcastMatches) > 0) {
                $broadcastTime = $broadcastMatches[array_key_first($broadcastMatches)];
                $this->getGame()->getPlugin()->getServer()->broadcastMessage(self::PREFIX . ' The border will shrink to ' . $borderSize . 'x' . $borderSize . ' in ' . $broadcastTime . ' second(s)');
            }

            if (GameValues::$RUNNING == ($borderTime * 60)) {
                $this->executeShrink();
            }
        }
    }
}
