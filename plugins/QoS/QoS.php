<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\QoS;

class QoS extends \Piwik\Plugin
{
	public function registerEvents()
	{
		return array(
			'AssetManager.getJavaScriptFiles'   => 'getJavaScriptFiles',
			'AssetManager.getStylesheetFiles'   => 'getStylesheetFiles',
		);
	}

	public function getStylesheetFiles(&$stylesheets)
	{
		$stylesheets[] = "plugins/QoS/stylesheets/qos.less";
	}

	public function getJavaScriptFiles(&$files)
	{
		$files[] = 'plugins/QoS/javascripts/jqplot.meterGaugeRenderer.js';
		$files[] = 'plugins/QoS/javascripts/qosMeterGauge.js';
        $files[] = 'plugins/QoS/javascripts/qos.js';
	}
}