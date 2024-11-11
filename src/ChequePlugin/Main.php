<?php

namespace ChequePlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use jojoe77777\FormAPI\SimpleForm;
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener {

    private $cooldowns = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
    }

    public function onCommand(\pocketmine\command\CommandSender $sender, \pocketmine\command\Command $command, string $label, array $args): bool {
        if($command->getName() === "cheque") {
            if($sender instanceof Player) {
                $this->openChequeUI($sender);
            } else {
                $sender->sendMessage("§cEste comando só pode ser usado no jogo.");
            }
            return true;
        }
        return false;
    }

    public function openChequeUI(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    $this->createCheque($player);
                    break;
                case 1:
                    $player->sendMessage("§aSeu saldo atual é: " . EconomyAPI::getInstance()->myMoney($player) . " moedas.");
                    break;
                case 2:
                    $player->sendMessage("");
                    break;
            }
        });

        $form->setTitle("Cheque");
        $form->addButton("Sacar Dinheiro");
        $form->addButton("Ver Saldo");
        $form->addButton("Fechar UI");
        $player->sendForm($form);
    }

    public function createCheque(Player $player): void {
        $moneyValues = [1000, 5000, 10000, 50000, 100000];
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($moneyValues) {
            if ($data === null || !isset($moneyValues[$data])) {
                return;
            }

            $amount = $moneyValues[$data];

            if (EconomyAPI::getInstance()->myMoney($player) >= $amount) {
                if (!isset($this->cooldowns[$player->getName()]) || time() - $this->cooldowns[$player->getName()] >= 60) {
                    $this->cooldowns[$player->getName()] = time(); // cooldown  60s

                    EconomyAPI::getInstance()->reduceMoney($player, $amount);
                    $item = Item::get(Item::PAPER);
                    $item->setCustomName("§eCheque de $amount moedas");
                    $player->getInventory()->addItem($item);

                    $player->sendMessage("§aVocê criou um cheque de $amount moedas.");
                } else {
                    $player->sendMessage("§cPor favor, aguardar antes de criar outro cheque.");
                }
            } else {
                $player->sendMessage("§cVocê não tem dinheiro suficiente para criar este cheque.");
            }
        });

        $form->setTitle("Sacar Dinheiro");
        foreach ($moneyValues as $value) {
            $form->addButton("Cheque de $value moedas");
        }
        $player->sendForm($form);
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if ($item->getId() === Item::PAPER && $item->hasCustomName()) {
            $customName = $item->getCustomName();

            
            if (strpos($customName, "Cheque de") !== false) {
                preg_match("/Cheque de (\d+) moedas/", $customName, $matches);
                if (isset($matches[1])) {
                    $amount = (int) $matches[1];
  
  
  
                    EconomyAPI::getInstance()->addMoney($player, $amount);
                    $player->sendMessage("§aVocê resgatou um cheque de $amount moedas!");


                    $player->getInventory()->setItemInHand(Item::get(Item::AIR));
                }
            }
        }
    }
}
