<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\command;

use alvin0319\AwaitCommand\AwaitCommand;
use alvin0319\EconomyAPI\data\Currency;
use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\form\CurrencyForm;
use alvin0319\EconomyAPI\model\CurrencyUpdateModel;
use alvin0319\EconomyAPI\registry\CurrencyRegistry;
use alvin0319\EconomyAPI\service\EconomyService;
use alvin0319\EconomyAPI\utils\CurrencyFormatter;
use alvin0319\StarGatePolyfill\StarGatePolyfill;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use skh6075\ExtensionPlugin\Message;
use function skh6075\ExtensionPlugin\assumeNotNull;

final class CurrencyCommand extends AwaitCommand implements PluginOwned{
	use PluginOwnedTrait;

	private readonly CurrencyForm $form;

	public function __construct(
		private readonly EconomyService $service,
		private readonly CurrencyRegistry $registry
	){
		parent::__construct("재화관리", "서버의 재화를 관리합니다.", "/재화관리 <추가/삭제/기본자금설정/표기설정/거래여부설정/기본재화설정/목록>");
		$this->setPermission("economyapi.command.currency");
		$this->owningPlugin = EconomyAPI::getInstance();
		$this->form = new CurrencyForm($service, $registry);
	}

	public function executeAsync(CommandSender $sender, string $commandLabel, array $args) : \Generator{
		if(!$sender instanceof Player){
			Message::sendPlayerOnlyMessage($sender);
			return;
		}
		if(count($args) < 1){
			Message::warn($sender, "사용법: §f/재화관리 <추가/삭제/기본자금설정/표기설정/거래여부설정/기본재화설정/목록>");
			return;
		}
		try{
			switch(assumeNotNull(array_shift($args))){
				case "추가":
					yield from $this->form->executeCreate($sender);
					break;
				case "삭제":
					yield from $this->form->executeDelete($sender);
					break;
				case "기본자금설정":
					if(count($args) < 2){
						Message::warn($sender, "사용법: §f/재화관리 기본소지금설정 <재화ID> <기본소지금>");
						return;
					}
					$currencyId = (int) assumeNotNull(array_shift($args));
					$balanceString = assumeNotNull(array_shift($args));
					$result = $this->registry->find($currencyId);
					if($result === null){
						Message::warn($sender, "해당 ID에 맞는 재화를 찾지 못했습니다.");
						return;
					}
					if(!is_numeric($balanceString) || ($balance = (int) $balanceString) < 0){
						Message::warn($sender, "소지금은 정수로만 입력이 가능하며, 0 보다 커야합니다.");
						return;
					}
					$result->defaultBalance = $balance;
					yield from $this->service->updateCurrency($result);
					StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_CURRENCY_UPDATE, CurrencyUpdateModel::update($result));
					Message::success($sender, "재화 §f" . $result->name . "§a의 기본 소지금을 §f" . CurrencyFormatter::kindly($balance, $result) . "§r§a 으(로) 수정했습니다.");
					break;
				case "표기설정":
					if(count($args) < 2){
						Message::warn($sender, "사용법: §f/재화관리 표기설정 <재화ID> <표기>");
						return;
					}
					$currencyId = (int) assumeNotNull(array_shift($args));
					$symbol = assumeNotNull(array_shift($args));
					$result = $this->registry->find($currencyId);
					if($result === null){
						Message::warn($sender, "해당 ID에 맞는 재화를 찾지 못했습니다.");
						return;
					}
					$result->symbol = $symbol;
					yield from $this->service->updateCurrency($result);
					StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_CURRENCY_UPDATE, CurrencyUpdateModel::update($result));
					Message::success($sender, "재화 §f" . $result->name . "§a의 표기를 §f" . CurrencyFormatter::kindly($result->defaultBalance, $result) . "§r§a 으(로) 수정했습니다.");
					break;
				case "거래여부설정":
					if(count($args) < 2){
						Message::warn($sender, "사용법: §f/재화관리 거래여부설정 <재화ID> <true/false>");
						return;
					}
					$currencyId = (int) assumeNotNull(array_shift($args));
					$canTransaction = assumeNotNull(array_shift($args)) === "true";
					$result = $this->registry->find($currencyId);
					if($result === null){
						Message::warn($sender, "해당 ID에 맞는 재화를 찾지 못했습니다.");
						return;
					}
					$result->canTransaction = $canTransaction;
					yield from $this->service->updateCurrency($result);
					StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_CURRENCY_UPDATE, CurrencyUpdateModel::update($result));
					Message::success($sender, "재화 §f" . $result->name . "§a의 거래가능여부를 §f" . ($canTransaction ? "가능" : "불가능") . "§r§a 으(로) 수정했습니다.");
					break;
				case "기본재화설정":
					if(count($args) < 1){
						Message::warn($sender, "사용법: §f/재화관리 기본재화설정 <재화ID>");
						return;
					}
					$currencyId = (int) assumeNotNull(array_shift($args));
					$result = $this->registry->find($currencyId);
					if($result === null){
						Message::warn($sender, "해당 ID에 맞는 재화를 찾지 못했습니다.");
						return;
					}
					$result->isDefault = true;
					yield from $this->service->updateCurrency($result);
					StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_CURRENCY_UPDATE, CurrencyUpdateModel::update($result));
					Message::success($sender, "§f" . $result->name . "§a 재화를 서버 기본 재화로 설정했습니다.");
					break;
				case "목록":
					$currencies = $this->registry->getAll();
					if($currencies !== []){
						Message::log($sender, "서버에 등록된 재화: §f" . implode(", ", array_map(static function(Currency $currency) : string{
								return $currency->isDefault ? "§a" . $currency->name . "§7 (ID=$currency->id)" : "§f" . $currency->name . "§7 (ID=$currency->id)";
							}, $currencies)));
					}else{
						Message::alert($sender, "서버에 등록된 재화가 없습니다.");
					}
					break;
			}
		}catch(\Throwable $e){
			Message::sendCommandRootError($this, $sender, $e);
		}
	}
}