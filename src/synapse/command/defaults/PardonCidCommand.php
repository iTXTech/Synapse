<?php
namespace synapse\command\defaults;

use synapse\command\Command;
use synapse\command\CommandSender;
use synapse\event\TranslationContainer;
use synapse\utils\TextFormat;

class PardonCidCommand extends VanillaCommand{

	public function __construct($name){
		parent::__construct(
			$name,
			"%synapse.command.unban.cid.description",
			"%commands.unbancid.usage"
		);
		$this->setPermission("synapse.command.pardoncid");
	}

	public function execute(CommandSender $sender, $currentAlias, array $args){
		if(!$this->testPermission($sender)){
			return \true;
		}

		if(\count($args) !== 1){
			$sender->sendMessage(new TranslationContainer("commands.generic.usage", [$this->usageMessage]));

			return \false;
		}

		$sender->getServer()->getCIDBans()->remove($args[0]);

		Command::broadcastCommandMessage($sender, new TranslationContainer("commands.unban.success", [$args[0]]));

		return \true;
	}
}
