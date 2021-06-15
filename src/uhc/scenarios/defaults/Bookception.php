<?php

declare(strict_types=1);

namespace uhc\scenarios\defaults;

use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use uhc\player\disconnect\DisconnectMob;
use uhc\player\GamePlayer;
use uhc\scenarios\modules\timebomb\TimebombTile;
use uhc\scenarios\Scenario;

/**
 * Class Bookception
 * @package uhc\scenarios\defaults
 */
class Bookception extends Scenario
{

    /**
     * Bookception constructor.
     */
    public function __construct()
    {
        parent::__construct('BookCeption', 'Upon a player\'s death, an enchanted book will drop in his stuff', Item::get(Item::ENCHANTED_BOOK), self::PRIORITY_LOW);
    }

    /**
     * @param EntityDeathEvent $event
     */
    public function handleEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof DisconnectMob && $entity->getData() != null)
            $this->dropBook($entity);
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function handleDeath(PlayerDeathEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof GamePlayer && $player->isSpawned() && !$player->isInPractice())
            $this->dropBook($player);
    }

    /**
     * @param Living $living
     */
    private function dropBook(Living $living): void
    {
        if ($living instanceof DisconnectMob || $living instanceof GamePlayer) {
            $item = $this->getBook();

            if ($living->getGame()->getScenarios()->isActiveByName('TimeBomb') != null) {
                foreach ($living->getLevel()->getTiles() as $tile) {
                    if ($tile instanceof TimebombTile) {
                        if (!is_null($tile->getOwner()) && $tile->getOwner() == $living->getName())
                            $tile->getInventory()->addItem($item);
                    }
                }
            } else {
                $living->getLevel()->dropItem($living, $item);
            }
        }
    }

    /**
     * @return Item
     */
    private function getBook(): Item
    {
        //Enchant ID max = 22 because the rest is bad,
        $enchantId = mt_rand(0, 22);
        $enchantLevel = 1;
        $item = Item::get(Item::ENCHANTED_BOOK, 0, 1);

        switch ($enchantId) {
            case Enchantment::SHARPNESS:
            case Enchantment::BANE_OF_ARTHROPODS:
            case Enchantment::EFFICIENCY:
            case Enchantment::POWER:
            case Enchantment::SMITE:
                $enchantLevel = mt_rand(1, 5);
                break;

            case Enchantment::BLAST_PROTECTION:
            case Enchantment::FEATHER_FALLING:
            case Enchantment::FIRE_PROTECTION:
            case Enchantment::PROJECTILE_PROTECTION:
            case Enchantment::PROTECTION:
                $enchantLevel = mt_rand(1, 4);
                break;

            case Enchantment::DEPTH_STRIDER:
            case Enchantment::FORTUNE:
            case Enchantment::LOOTING:
            case Enchantment::RESPIRATION:
            case Enchantment::THORNS:
            case Enchantment::UNBREAKING:
                $enchantLevel = mt_rand(1, 3);
                break;

            case Enchantment::FIRE_ASPECT:
            case Enchantment::KNOCKBACK:
            case Enchantment::PUNCH:
                $enchantLevel = mt_rand(1, 2);
                break;

            case Enchantment::AQUA_AFFINITY:
            case Enchantment::FLAME:
            case Enchantment::INFINITY:
            case Enchantment::SILK_TOUCH:
                $enchantLevel = 1;
                break;
        }
        $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment($enchantId), $enchantLevel));
        return $item;
    }
}
