<?php

declare(strict_types=1);

namespace uhc\scenarios\defaults;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use uhc\event\GameStartEvent;
use uhc\game\utils\GameValues;
use uhc\player\disconnect\DisconnectMob;
use uhc\player\GamePlayer;
use uhc\scenarios\Scenario;

/**
 * Class Radar
 * @package uhc\scenarios\defaults
 */
class Radar extends Scenario
{

    /**
     * Radar constructor.
     */
    public function __construct()
    {
        parent::__construct('Radar', 'You can find the player closest to you after grace period, using compass', ItemFactory::get(ItemIds::COMPASS));
    }

    /**
     * @return Item
     */
    public function getItem(): Item
    {
        $item = ItemFactory::get(ItemIds::COMPASS);
        $item->setCustomName(TextFormat::LIGHT_PURPLE . 'Radar');
        $item->getNamedTag()->setInt('RadarScenario', 1);
        return $item;
    }

    /**
     * @param GamePlayer $player
     * @return array
     */
    private function findPlayer(GamePlayer $player): array
    {
        $players = array_values((array_filter($player->getServer()->getOnlinePlayers(), function (Player $p) use ($player): bool {
            return $p instanceof GamePlayer && $p->isSpawned() && $p !== $player;
        })) + (array_filter($player->getGame()->getLevel()->getEntities(), function (Entity $entity): bool {
                return $entity instanceof DisconnectMob && $entity->getData() != null;
            })));

        if (count($players) == 0) {
            return [];
        }
        uasort($players, function (Living $fistPlayer, Living $secondPlayer) use ($player) {
            return $fistPlayer->distance($player) <=> $secondPlayer->distance($player);
        });
        return [$players[0]->getName(), $players[0]->distance($player)];
    }

    /**
     * @param GameStartEvent $event
     */
    public function handleStart(GameStartEvent $event): void
    {
        $game = $event->getGame();

        foreach ($game->getPlayers('online') as $player)
            $player->getInventory()->addItem($this->getItem());
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function handleInteract(PlayerInteractEvent $event): void
    {
        $action = $event->getAction();
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if ($player instanceof GamePlayer && $player->getData()->isAlive() && GameValues::$RUNNING >= GameValues::$GRACE_PERIOD) {
            if ($item->getId() == ItemIds::COMPASS && $action == $event::RIGHT_CLICK_AIR) {
                $result = $this->findPlayer($player);

                if (count($result) == 0) {
                    $player->sendPopup(TextFormat::RED . 'No nearby players');
                } else {
                    $player->sendPopup(TextFormat::LIGHT_PURPLE . $result[0] . TextFormat::GRAY . ' is ' . TextFormat::LIGHT_PURPLE . (int) $result[1] . TextFormat::GRAY . ' block(s) away from you');
                }
            }
        }
    }
}
