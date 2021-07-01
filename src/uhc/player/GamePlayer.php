<?php

declare(strict_types=1);

namespace uhc\player;

use addon\player\AddonPlayer;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use uhc\game\Game;
use uhc\game\utils\GameState;
use uhc\game\utils\GameUtils;
use uhc\game\utils\GameValues;
use uhc\sessions\types\PlayerSession;
use uhc\sessions\types\TeamSession;
use uhc\UHCLoader;

/**
 * Class GamePlayer
 * @package uhc\player
 */
class GamePlayer extends AddonPlayer
{

    /** @var int */
    private $lastCheck = -1;
    /** @var bool */
    private $inPractice = false;
    /** @var array */
    public $recentBlocks = [];

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return UHCLoader::getInstance()->getGame();
    }

    /**
     * @return bool
     */
    public function isSpawned(): bool
    {
        return $this->spawned;
    }

    /**
     * @return bool
     */
    public function isInPractice(): bool
    {
        return $this->inPractice;
    }

    /**
     * @return PlayerSession|null
     */
    public function getData(): ?PlayerSession
    {
        return $this->getGame()->getSessions()->getPlayer($this);
    }

    /**
     * @return TeamSession|null
     */
    public function getTeam(): ?TeamSession
    {
        return $this->getData()->getTeam() != null ? $this->getGame()->getSessions()->getTeam($this->getData()->getTeam()) : null;
    }

    /**
     * @param bool $inPractice
     */
    public function setInPractice(bool $inPractice): void
    {
        $this->inPractice = $inPractice;
    }


    public function changeScoreboard(): void
    {
        $data = [];
        $data[] = TextFormat::WHITE . str_repeat(' ', 19);

        switch ($this->getGame()->getState()) {
            case GameState::WAITING:
                if (!$this->isInPractice())
                    $data[] = TextFormat::WHITE . ' Players: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('alive'));
                else {
                    $data[] = TextFormat::WHITE . ' Eliminations: ' . TextFormat::LIGHT_PURPLE . $this->getGame()->getPractice()->getKills($this);
                    $data[] = TextFormat::WHITE . ' In arena: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('practice'));
                }
                break;

            case GameState::SETUP:
                if (!$this->getGame()->isTeams())
                    $data[] = TextFormat::WHITE . ' Scattered players: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('scattering'));
                else
                    $data[] = TextFormat::WHITE . ' Scattered teams: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getTeams('scattering'));
                break;

            case GameState::STARTING:
                $data[] = TextFormat::WHITE . ' Start in: ' . TextFormat::LIGHT_PURPLE . GameValues::$STARTING;
                break;

            case GameState::RUNNING:
                $data[] = TextFormat::WHITE . ' Game Time: ' . TextFormat::LIGHT_PURPLE . gmdate('H:i:s', GameValues::$RUNNING);
                $data[] = TextFormat::WHITE . ' Remaining: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('alive')) . '/' . count($this->getGame()->getPlayers('scattering')) . ($this->getGame()->isTeams() ? ' ' . TextFormat::GRAY . '(' . count($this->getGame()->getTeams('alive')) . ')' : '');

                if (!$this->getData()->isHost())
                    $data[] = TextFormat::WHITE . ' Eliminations: ' . TextFormat::LIGHT_PURPLE . $this->getData()->getEliminations() . ($this->getGame()->isTeams() && $this->getTeam() != null ? TextFormat::GRAY . ' (' . $this->getTeam()->getEliminations() . ')' : '');
                else
                    $data[] = TextFormat::WHITE . ' Spectators: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('spectators'));
                $data[] = TextFormat::WHITE . ' Border: ' . TextFormat::LIGHT_PURPLE . $this->getGame()->getBorder()->getSize() . ($this->getGame()->getBorder()->canShrink() ? ' ' . TextFormat::GRAY . '(' . TextFormat::RED . ((($this->getGame()->getBorder()->getNextBorderTime() * 60) - GameValues::$RUNNING) >= 60 ? floor((($this->getGame()->getBorder()->getNextBorderTime() * 60) - GameValues::$RUNNING) / 60) + 1 . 'm' : (($this->getGame()->getBorder()->getNextBorderTime() * 60) - GameValues::$RUNNING) . 's') . TextFormat::GRAY . ')' : '');

                if ($this->getData()->isHost()) {
                    $data[] = '    ';
                    $data[] = TextFormat::WHITE . ' TPS: ' . TextFormat::LIGHT_PURPLE . $this->getServer()->getTicksPerSecond();
                }
                break;

            case GameState::RESTARTING:
                /** @var TeamSession[]|PlayerSession[] $objects */
                $objects = array_values(($this->getGame()->isTeams() ? $this->getGame()->getTeams('alive') : $this->getGame()->getPlayers('alives')));
                $object = $objects[0];
                $data[] = TextFormat::WHITE . ' Time played: ' . TextFormat::LIGHT_PURPLE . gmdate('H:i:s', GameValues::$RUNNING);
                $data[] = '  ';
                $data[] = TextFormat::WHITE . (!$this->getGame()->isTeams() ? ' Winner: ' . TextFormat::LIGHT_PURPLE . $object->getName(true) : ' Team winner: ' . TextFormat::LIGHT_PURPLE . '#' . $object->getTeamInt());
                $data[] = TextFormat::WHITE . '  - ' . (!$this->getGame()->isTeams() ? 'Kills: ' . TextFormat::LIGHT_PURPLE . $object->getEliminations() : ' Team kills: ' . TextFormat::LIGHT_PURPLE . $object->getEliminations());
                break;
        }
        $data[] = ' ';
        $data[] = TextFormat::GRAY . ' @GameFeedWeekom';
        $this->getScoreboard()->clearScoreboard();

        for ($i = 0; $i < count($data); $i++) {
            $this->getScoreboard()->addLine($i, $data[$i]);
        }
    }

    public function updateScoreboard(): void
    {
        switch ($this->getGame()->getState()) {
            case GameState::WAITING:
                if (!$this->isInPractice())
                    $this->getScoreboard()->addLine(1, TextFormat::WHITE . ' Players: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('alive')));
                else {
                    $this->getScoreboard()->addLine(1, TextFormat::WHITE . ' Eliminations: ' . TextFormat::LIGHT_PURPLE . $this->getGame()->getPractice()->getKills($this));
                    $this->getScoreboard()->addLine(2, TextFormat::WHITE . ' In arena: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('practice')));
                }
                break;

            case GameState::SETUP:
                if (!$this->getGame()->isTeams())
                    $this->getScoreboard()->addLine(1, TextFormat::WHITE . ' Scattered players: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('scattering')));
                else
                    $this->getScoreboard()->addLine(1, TextFormat::WHITE . ' Scattered teams: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getTeams('scattering')));
                break;

            case GameState::STARTING:
                $this->getScoreboard()->addLine(1, TextFormat::WHITE . ' Start in: ' . TextFormat::LIGHT_PURPLE . GameValues::$STARTING);
                break;

            case GameState::RUNNING:
                $this->getScoreboard()->addLine(1, TextFormat::WHITE . ' Game Time: ' . TextFormat::LIGHT_PURPLE . gmdate('H:i:s', GameValues::$RUNNING));
                $this->getScoreboard()->addLine(2, TextFormat::WHITE . ' Remaining: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('alives')) . '/' . count($this->getGame()->getPlayers('scattering')) . ($this->getGame()->isTeams() ? ' ' . TextFormat::GRAY . '(' . count($this->getGame()->getTeams('alives')) . ')' : ''));

                if (!$this->getData()->isHost())
                    $this->getScoreboard()->addLine(3, TextFormat::WHITE . ' Eliminations: ' . TextFormat::LIGHT_PURPLE . $this->getData()->getEliminations() . ($this->getGame()->isTeams() && $this->getTeam() != null ? TextFormat::GRAY . ' (' . $this->getTeam()->getEliminations() . ')' : ''));
                else
                    $this->getScoreboard()->addLine(3, TextFormat::WHITE . ' Spectators: ' . TextFormat::LIGHT_PURPLE . count($this->getGame()->getPlayers('spectators')));
                $this->getScoreboard()->addLine(4, TextFormat::WHITE . ' Border: ' . TextFormat::LIGHT_PURPLE . $this->getGame()->getBorder()->getSize() . ($this->getGame()->getBorder()->canShrink() ? ' ' . TextFormat::GRAY . '(' . TextFormat::RED . ((($this->getGame()->getBorder()->getNextBorderTime() * 60) - GameValues::$RUNNING) >= 60 ? floor((($this->getGame()->getBorder()->getNextBorderTime() * 60) - GameValues::$RUNNING) / 60) + 1 . 'm' : (($this->getGame()->getBorder()->getNextBorderTime() * 60) - GameValues::$RUNNING) . 's') . TextFormat::GRAY . ')' : ''));

                if ($this->getData()->isHost())
                    $this->getScoreboard()->addLine(6, TextFormat::WHITE . ' TPS: ' . TextFormat::LIGHT_PURPLE . $this->getServer()->getTicksPerSecond());
                else {
                    $dnd = $this->getData()->getDoNotDisturb();

                    if ($dnd != null && (time() - $dnd['time']) < 20) {
                        $this->getScoreboard()->addLine(5, TextFormat::WHITE . ' Do not disturb: ' . TextFormat::LIGHT_PURPLE . (($dnd['time'] + 20) - time()));
                        $this->getScoreboard()->addLine(6, '  ');
                        $this->getScoreboard()->addLine(7, TextFormat::GRAY . ' @GameFeedWeekom');
                    } else {
                        $this->getScoreboard()->addLine(5, '  ');
                        $this->getScoreboard()->addLine(6, TextFormat::GRAY . ' @GameFeedWeekom');
                    }
                }
                break;
        }
    }

    private function setCoordinates(): void
    {
        $pk = new GameRulesChangedPacket();
        $pk->gameRules = ['showCoordinates' => [1, true, true]];
        $this->directDataPacket($pk);
    }

    /**
     * @param int $gamemode
     */
    public function reset(int $gamemode = 0): void
    {
        $this->setGamemode($gamemode);
        $this->getInventory()->clearAll();
        $this->getCursorInventory()->clearAll();
        $this->getArmorInventory()->clearAll();
        $this->removeAllEffects();
        $this->setHealth(20);
        $this->setFood(20);
        $this->setXpProgress(0.0);
        $this->setXpLevel(0);
        $this->extinguish();
    }

    public function join(): void
    {
        parent::join();

        if ($this->getGame()->getSessions()->getPlayer($this) == null)
            $this->getGame()->getSessions()->addPlayer($this);

        if ($this->getData() != $this->getDeviceOS(true)) {
            $this->getData()->setDevice($this->getDeviceOS(true));
            $this->getData()->setInput($this->getInput(true));
        }

        switch ($this->getGame()->getState()) {
            case GameState::WAITING:
                $this->reset();
                $this->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
                $this->getInventory()->setContents(GameUtils::getKit('waiting'));
                break;

            case GameState::SETUP:
                if ($this->isAlive() && !$this->getData()->isScattering()) {
                    if (!$this->getGame()->isTeams()) {
                        $this->reset();
                        $this->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
                    } else {
                        if ($this->getTeam() == null) {
                            $this->reset();
                            $this->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
                            $this->getGame()->getSessions()->addTeam($this);
                        } else {
                            if ($this->getTeam()->isScattering()) {
                                $this->reset(3);
                                $this->getData()->setSpectator(true);
                                $this->teleport($this->getGame()->getLevel()->getSpawnLocation()->add(0, 3, 0));
                                $this->getInventory()->setContents(GameUtils::getKit('spectator'));
                            }
                        }
                    }
                }
                $this->getGame()->getDisconnectManager()->join($this);
                break;

            case GameState::STARTING:
            case GameState::RUNNING:
            case GameState::RESTARTING:
                if ($this->getData()->isAlive() && !$this->getData()->isScattering()) {
                    $this->reset(3);
                    $this->getData()->setSpectator(true);
                    $this->teleport($this->getGame()->getLevel()->getSpawnLocation()->add(0, 3, 0));
                    $this->getInventory()->setContents(GameUtils::getKit('spectator'));
                } else {
                    if ($this->getData()->isAlive()) {
                        $this->getGame()->getDisconnectManager()->join($this);
                    } elseif ($this->getData()->isSpectator()) {
                        $this->reset(3);
                        $this->getInventory()->setContents(GameUtils::getKit('spectator'));
                    }
                }

                if (GameValues::$RUNNING < GameValues::$GRACE_PERIOD)
                    $this->getGame()->getBossBar()->addPlayer($this);
                break;
        }
        $this->setCoordinates();
        $this->changeScoreboard();
        $this->addPermissions();
    }

    public function quit(): void
    {
        $this->clearPermissions();

        switch ($this->getGame()->getState()) {
            case GameState::SETUP:
            case GameState::STARTING:
            case GameState::RUNNING:
                if ($this->getData()->isAlive() && $this->getData()->isScattering())
                    $this->getGame()->getDisconnectManager()->addDisconnected($this);
                break;
        }
    }

    public function clearPermissions(): void
    {
        $attachment = $this->addAttachment(UHCLoader::getInstance());
        $attachment->clearPermissions();
    }

    public function addPermissions(): void
    {
        $config = UHCLoader::getInstance()->getConfig()->getAll();
        $attachment = $this->addAttachment(UHCLoader::getInstance());
        $permissions = $this->getRank() != null ? (array)$config['ranks'][$this->getRank()]['permissions'] : [];

        foreach ($permissions as $permission)
            $attachment->setPermission($permission, true);
    }

    /**
     * @return string
     */
    public function getTeamFormat(): string
    {
        return $this->getTeam() != null ? $this->getTeam()->getFormat() . ' ' : '';
    }

    /**
     * @return string
     */
    public function getRankFormat(): string
    {
        $config = UHCLoader::getInstance()->getConfig()->getAll();
        $rank = $this->getRank();
        return $rank != null ? (string)$config['ranks'][$rank]['prefix'] . ' ' : '';
    }

    /**
     * @return string
     */
    public function getPrefixFormat(): string
    {
        $config = UHCLoader::getInstance()->getConfig()->getAll();
        $prefix = $this->getPrefix();
        return $prefix != null ? (string)$config['prefix'][$prefix] . ' ' : '';
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        $format = TextFormat::GRAY . $this->getName(true) . ':';

        if ($this->getData()->isHost())
            $format = TextFormat::YELLOW . '[Host] ' . $format;
        elseif ($this->getData()->isSpectator())
            $format = TextFormat::GRAY . '[Spect] ' . $format;

        if ($this->getRank() != null)
            $format = $this->getRankFormat() . $format;

        if (!$this->getData()->isHost() && $this->getTeam() != null)
            $format = $this->getTeamFormat() . $format;

        if ($this->getPrefix() != null)
            $format = $this->getPrefixFormat() . $format;
        return $format;
    }

    public function processMostRecentMovements(): void
    {
        $now = microtime(true);

        if ($now - $this->lastCheck > 1) {
            $this->lastCheck = $now;

            if ($this->getGame()->getState() == GameState::RUNNING && !$this->getGame()->getBorder()->inBorder($this))
                $this->getGame()->getBorder()->teleport($this);
        }
        parent::processMostRecentMovements();
    }

    /**
     * @param int $currentTick
     * @return bool
     */
    public function onUpdate(int $currentTick): bool
    {
        if ($this->isSpawned()) {
            if ($currentTick % 20 === 0) {
                $this->updateScoreboard();
                $this->setNameTag(($this->getData()->isHost() ? TextFormat::YELLOW . '[Host] ' : '') . ($this->getGame()->isTeams() ? ($this->getTeam() != null ? $this->getTeam()->getFormat() . ' ' : '') : '') . TextFormat::RED . $this->getName(true) . TextFormat::WHITE . ' ' . round($this->getHealth() / 2, 2) . TextFormat::RED . '❤' . PHP_EOL . TextFormat::YELLOW . $this->getDeviceOS(true) . TextFormat::GRAY . ' | ' . TextFormat::YELLOW . $this->getInput(true));
            }

            if ($currentTick % 40 === 0) {
                # Vanish
                $data = UHCLoader::getInstance()->getGame()->getStaffManager()->getData($this);

                if ($data != null && $data['vanish']) {
                    $players = UHCLoader::getInstance()->getServer()->getOnlinePlayers();
                    array_walk($players, function (Player $p) {
                        if ($p instanceof GamePlayer && $p->isSpawned())
                            if (!$p->getData()->isHost())
                                $p->hidePlayer($this);
                            else
                                $p->showPlayer($this);
                    });
                }
            }
        }
        
        return parent::onUpdate($currentTick);
    }
}
