<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\command;

use alvin0319\AwaitCommand\AwaitCommand;
use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\registry\CurrencyRegistry;
use alvin0319\EconomyAPI\service\EconomyService;
use alvin0319\EconomyAPI\utils\CurrencyFormatter;
use alvin0319\XuidCore\XuidCore;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use skh6075\ExtensionPlugin\Message;
use function skh6075\ExtensionPlugin\assumeNotNull;

final class TopMoneyCommand extends AwaitCommand implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(
		private readonly EconomyService $service,
		private readonly CurrencyRegistry $registry
	){
		parent::__construct("돈순위", "돈 순위를 확인합니다.", "/돈순위 [화폐] [페이지]");
		$this->setPermission("economyapi.command.give");
		$this->owningPlugin = EconomyAPI::getInstance();
	}

	public function executeAsync(CommandSender $sender, string $commandLabel, array $args) : \Generator{
		$currency = EconomyAPI::getInstance()->getDefaultCurrency();
		$page = 1;
		if(count($args) > 0){
			$currencyOrPage = assumeNotNull(array_shift($args));
			if(is_numeric($currencyOrPage)){
				$page = (int) $currencyOrPage;
			}else{
				$currency = $this->registry->getCurrencyOrDefault($currencyOrPage);
			}
		}

		if(count($args) > 0){
			$pageString = assumeNotNull(array_shift($args));
			if(is_numeric($pageString)){
				$page = (int) $pageString;
			}
		}
		if($page < 1){
			$page = 1;
		}

		try{
			$rankings = yield from $this->service->getTopBalances($currency->id, $page, 10);
			if(count($rankings) === 0){
				Message::alert($sender, "순위 정보가 없습니다.");
				return;
			}

			Message::log($sender, "돈 순위를 확인합니다. §f(§g{$page}페이지§f)");
			$rank = ($page - 1) * 10 + 1;
			for($i = 0, $iMax = count($rankings); $i < $iMax; $i ++){
				$prefix = ($iMax === ($i + 1) ? "┗━" : "┣━");
				$data = $rankings[$i];
				$xuid = $data["xuid"];
				$balance = (int) $data["balance"];

				$playerName = yield from XuidCore::lookupPlayerName($xuid);
				if($playerName === null){
					$playerName = "NONE";
				}
				$sender->sendMessage("§r§u $prefix §r§l§e{$rank}위§r§f $playerName §f" . CurrencyFormatter::kindly($balance, $currency));
				++$rank;
			}

		}catch(\Throwable $e){
			Message::sendCommandRootError($this, $sender, $e);
		}
	}
}