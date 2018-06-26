<?php
namespace FreePBX\modules\Dictate;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
  public function runRestore($jobid){
      $configs = $this->getConfigs();
      foreach ($configs as $ext => $conf) {
          $this->FreePBX->Dictate->add($ext, $conf['enabled'], $conf['format'], $conf['email'], $conf['from']);
      }
  }
}