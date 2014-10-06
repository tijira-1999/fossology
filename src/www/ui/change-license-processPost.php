<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Util\ChangeLicenseUtility;
use Fossology\Lib\Util\LicenseOverviewPrinter;

define("TITLE_changeLicProcPost", _("Private: Change license file post"));

class changeLicenseProcessPost extends FO_Plugin
{

  /**
   * @var ClearingDao;
   */
  private $clearingDao;

  /**
   * @var ChangeLicenseUtility
   */
  private $changeLicenseUtility;

  /**
   * @var LicenseOverviewPrinter
   */
  private $licenseOverviewPrinter;


  function __construct()
  {
    $this->Name = "change-license-processPost";
    $this->Title = TITLE_changeLicProcPost;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->OutputType = 'JSON';
    $this->OutputToStdout = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->clearingDao = $container->get('dao.clearing');
    $this->changeLicenseUtility = $container->get('utils.change_license_utility');
    $this->licenseOverviewPrinter =  $container->get('utils.license_overview_printer');
  }

  /**
   * \brief change bucket accordingly when change license of one file
   */
  // TODO:  Understand Buckets and modify then
  function ChangeBuckets()
  {
    global $SysConf;
    global $PG_CONN;

    $uploadId = GetParm("upload", PARM_STRING);
    $uploadTreeId = GetParm("item", PARM_STRING);

    $sql = "SELECT bucketpool_fk from bucket_ars where upload_fk = $uploadId;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $bucketpool_array = pg_fetch_all_columns($result, 0);
    pg_free_result($result);
    $buckets_dir = $SysConf['DIRECTORIES']['MODDIR'];
    /** rerun bucket on the file */
    foreach ($bucketpool_array as $bucketpool)
    {
      $command = "$buckets_dir/buckets/agent/buckets -r -t $uploadTreeId -p $bucketpool";
      exec($command, $output, $return_var);
    }
  }

  function OutputOpen()
  {
    header('Content-type: text/json');
    parent::OutputOpen();
  }
  
  
  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    $itemId = $_POST['uploadTreeId'];
    $remove = boolval( GetParm('remove', PARM_STRING) );
       
        
    if(array_key_exists('lic',$_POST)){
      $this->clearingDao->commentClearingDecision($_POST['unlic'], $itemId, $userId);
    }
    else if(array_key_exists('unlic',$_POST)){  //??
      $this->clearingDao->insertClearingDecisionTest($_POST['unlic'], $itemId, $userId);
    }
    else if(array_key_exists('type',$_POST)){
      if($remove)
        $this->clearingDao->insertClearingDecisionTest($_POST['licenseNumbersToBeSubmitted'], $itemId, $userId, $_POST['type'], $_POST['scope'], $_POST['comment'], $_POST['remark']);
      else
        $this->clearingDao->insertClearingDecisionTest($_POST['licenseNumbersToBeSubmitted'], $itemId, $userId, $_POST['type'], $_POST['scope'], $_POST['comment'], $_POST['remark']);
    }
    $clearingDecWithLicences = $this->clearingDao->getFileClearings($itemId);

    /** after changing one license, purge all the report cache */
    ReportCachePurgeAll();

    //Todo: Change sql statement of fossology/src/buckets/agent/leaf.c line 124 to take the newest valid license, then uncomment this line
    // $this->ChangeBuckets(); // change bucket accordingly
  
    
    print(json_encode(array(
      'tableClearing' => $this->changeLicenseUtility->printClearingTableInnerHtml($clearingDecWithLicences, $userId),
      'recentLicenseClearing' => $this->licenseOverviewPrinter->createRecentLicenseClearing($clearingDecWithLicences))));
  }


}

$NewPlugin = new changeLicenseProcessPost;
$NewPlugin->Initialize();


