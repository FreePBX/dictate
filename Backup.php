<?php
namespace FreePBX\modules\Dictate;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
  public function runBackup($id,$transaction){
    $this->addConfigs($this->FreePBX->Dictate->listAll());
  }
}