<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\QoS;
use Piwik\Piwik;
use Piwik\Settings\SystemSetting;

/**
 * Defines Settings.
 */
class Settings extends \Piwik\Plugin\Settings
{

	/** @var QoSSetting */
	public $qosSettings;

	/** @var speedDownload */
	public $speedDownload;

	/** @var httpCode */
	public $httpCode;

	/** @var cacheHit */
	public $cacheHit;

	/** @var isp */
	public $isp;

	/** @var country */
	public $country;

	protected function init()
	{
		$this->setIntroduction(Piwik::translate('QoS_SettingIntro'));

		$this->createQoSSetting();
		$this->createSpeedDownloadSetting();
		$this->createHttpCodeSetting();
		$this->createCacheHitSetting();
		$this->createIspSetting();
		$this->createCountrySetting();
	}

	private function createQoSSetting()
	{
		$this->qosSettings        = new SystemSetting('qosSettings', Piwik::translate('QoS_SettingLabel') );
		$this->qosSettings->type  = static::TYPE_STRING;
		$this->qosSettings->uiControlType = static::CONTROL_TEXT;
		$this->qosSettings->description     = Piwik::translate('QoS_SettingDescription');
		$this->qosSettings->inlineHelp      = Piwik::translate('QoS_ServerSettingHelp');
		$this->qosSettings->defaultValue    = false;

		$this->addSetting($this->qosSettings);
	}

	private function createHttpCodeSetting()
	{
		$this->httpCode        = new SystemSetting('httpCode', 'Metrics Http Code');
		$this->httpCode->type  = static::TYPE_ARRAY;
		$this->httpCode->uiControlType = static::CONTROL_MULTI_SELECT;
		$this->httpCode->availableValues  = array('request_count_200' => 'Http code 200', 'request_count_204' => 'Http code 200', 'request_count_206' => 'Http code 206');
		$this->httpCode->description   = 'The value will be only displayed in the following http code 2xx';
		$this->httpCode->defaultValue  = array(
			'2xx'   => array('request_count_200','request_count_204','request_count_206'),
			'3xx'   => array('request_count_301','request_count_302','request_count_304'),
			'4xx'   => array('request_count_400','request_count_404'),
			'5xx'   => array('request_count_500','request_count_502','request_count_503','request_count_504')
		);
		$this->httpCode->readableByCurrentUser = true;

		$this->addSetting($this->httpCode);
	}

	private function createCacheHitSetting()
	{
		$this->cacheHit        = new SystemSetting('cacheHit', 'Metrics Cache Hit');
		$this->cacheHit->type  = static::TYPE_ARRAY;
		$this->cacheHit->uiControlType = static::CONTROL_MULTI_SELECT;
		// $this->cacheHit->availableValues  = array();
		$this->cacheHit->description   = 'The value will be only displayed in the following http code 2xx in only isp';
		$this->cacheHit->defaultValue  = array(
			'vnpt'          => array ('isp_request_count_200_vnpt','isp_request_count_206_vnpt'),
			'vinaphone'     => array ('isp_request_count_200_vinaphone','isp_request_count_206_vinaphone'),
			'viettel'   => array ('isp_request_count_200_viettel','isp_request_count_206_viettel'),
			'fpt'       => array ('isp_request_count_200_fpt','isp_request_count_206_fpt'),
			'mobiphone' => array ('isp_request_count_200_mobiphone','isp_request_count_206_mobiphone'),
		);
		$this->cacheHit->readableByCurrentUser = true;

		$this->addSetting($this->cacheHit);
	}

	private function createSpeedDownloadSetting()
	{
		$this->speedDownload        = new SystemSetting('speedDownload', 'Metrics Cache Hit');
		$this->speedDownload->type  = static::TYPE_STRING;
		$this->speedDownload->uiControlType = static::CONTROL_TEXT;
		// $this->cacheHit->availableValues  = array();
		$this->speedDownload->description   = 'The value will be only displayed in the following speed download';
		$this->speedDownload->defaultValue  = 'avg_speed';
		$this->speedDownload->readableByCurrentUser = true;

		$this->addSetting($this->speedDownload);
	}

	private function createIspSetting()
	{
		$this->isp        = new SystemSetting('isp', 'Metrics of Isp');
		$this->isp->type  = static::TYPE_ARRAY;
		$this->isp->uiControlType = static::CONTROL_MULTI_SELECT;
		// $this->cacheHit->availableValues  = array();
		$this->isp->description   = 'The value will be only displayed in the following http code 2xx in only isp';
		$this->isp->defaultValue  = array(
			'vnpt'          => array ('isp_request_count_200_vnpt','isp_request_count_206_vnpt'),
			'vinaphone'     => array ('isp_request_count_200_vinaphone','isp_request_count_206_vinaphone'),
			'viettel'   => array ('isp_request_count_200_viettel','isp_request_count_206_viettel'),
			'fpt'       => array ('isp_request_count_200_fpt','isp_request_count_206_fpt'),
			'mobiphone' => array ('isp_request_count_200_mobiphone','isp_request_count_206_mobiphone'),
		);
		$this->isp->readableByCurrentUser = true;

		$this->addSetting($this->isp);
	}

	private function createCountrySetting()
	{
		$this->country        = new SystemSetting('country', 'Metrics of Isp');
		$this->country->type  = static::TYPE_ARRAY;
		$this->country->uiControlType = static::CONTROL_MULTI_SELECT;
		// $this->cacheHit->availableValues  = array();
		$this->country->description   = 'The value will be only displayed in the following speed download';
		$this->country->defaultValue  = array('country_request_count_200_VN','country_request_count_200_US','country_request_count_200_CN');

		$this->country->readableByCurrentUser = true;

		$this->addSetting($this->country);
	}
}
