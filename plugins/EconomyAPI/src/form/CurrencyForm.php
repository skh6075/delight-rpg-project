<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\form;

use alvin0319\AwaitForm\AwaitCustomForm;
use alvin0319\EconomyAPI\data\Currency;
use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\model\CurrencyUpdateModel;
use alvin0319\EconomyAPI\registry\CurrencyRegistry;
use alvin0319\EconomyAPI\service\EconomyService;
use alvin0319\ExtensionPlugin\Message;
use alvin0319\ExtensionPlugin\Unicodes;
use alvin0319\StarGatePolyfill\StarGatePolyfill;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Label;
use dktapps\pmforms\element\Toggle;
use dktapps\pmforms\MenuOption;
use pocketmine\player\Player;

final readonly class CurrencyForm{
	public function __construct(
		private EconomyService $service,
		private CurrencyRegistry $registry
	){}

	public function executeCreate(Player $player): \Generator{
		$form = new AwaitCustomForm("§l재화", [
			new Input("name", "§e■§f 재화 이름을 적어주세요."),
			new Input("symbol", "§e■§f 재화 표기를 적어주세요."),
			new Input("default_balance", "§e■§f 재화 기본 소지금을 적어주세요."),
			new Toggle("can_transaction", "§e■§f 재화 거래 가능 여부를 설정해주세요.", defaultValue: false),
			new Toggle("is_default", "§e■§f 재화를 서버 기본 재화로 설정하시겠습니까?", defaultValue: false)
		]);
		/** @var CustomFormResponse|null $response */
		$response = yield from $form->send($player);
		if($response === null){
			return;
		}
		$name = trim($response->getString("name"));
		$symbol = trim($response->getString("symbol"));
		$defaultBalanceString = trim($response->getString("default_balance"));
		if(!is_numeric($defaultBalanceString) || ($defaultBalance = (int) $defaultBalanceString) < 0){
			Message::alert($player, "재화의 기본 소지금은 정수로만 입력이 가능하며, 0 보다 커야합니다.");
			return;
		}
		$canTransaction = $response->getBool("can_transaction");
		$isDefault = $response->getBool("is_default");
		if($this->registry->getCurrencyOrNull($name) !== null){
			Message::warn($player, "§f$name §c으로 등록된 재화가 존재합니다.");
			return;
		}
		/** @var Currency $currency */
		$currency = yield from $this->service->createCurrency($name, $symbol, $defaultBalance, $canTransaction, $isDefault);
		StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_CURRENCY_UPDATE, CurrencyUpdateModel::register($currency));
		Message::success($player, "§f" . $currency->name . "§a 재화를 생성했습니다.");
	}

	public function executeDelete(Player $player): \Generator{
		$options = [];
		foreach($this->registry->getAll() as $currency){
			if($currency->isDefault){
				continue;
			}
			$options[] = new Toggle("$currency->id", "§e■§b $currency->name §f재화 삭제하기", defaultValue: false);
		}
		if($options === []){
			Message::alert($player, "삭제 가능한 재화가 없습니다.");
			return;
		}
		$form = new AwaitCustomForm("§l재화", array_merge([
			new Label("", implode(PHP_EOL, [
				"",
				"§r§f" . Unicodes::ALERT->toString() . " 삭제하실 재화를 선택해주세요.",
				"§r§f" . Unicodes::WARN->toString() . " 삭제 후 복구는 불가합니다.",
				"§r §f"
			]))
		], $options));
		/** @var CustomFormResponse|null $response */
		$response = yield from $form->send($player);
		if($response === null){
			return;
		}
		$deleted = [];
		foreach($this->registry->getAll() as $currency){
			if($response->getBool("$currency->id")){
				$deleted[] = $currency->name;
				yield from $this->service->deleteCurrency($currency);
				StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_CURRENCY_UPDATE, CurrencyUpdateModel::unregister($currency));
			}
		}
		if($deleted !== []){
			Message::success($player, "총 §f" . count($deleted) . "개§a의 재화가 삭제되었습니다.");
			Message::success($player, "삭제된 재화: §f" . implode(", ", $deleted));
		}
	}
}