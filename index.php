<?php

use Dugwood\Core\Configuration;
use Dugwood\Core\Checker\Html;

/**
 * ClickHeat : Fichier principal / Main file
 *
 * @author Yvan Taviaud - Dugwood - www.dugwood.com
 * @since 27/10/2006
 */
/* Remove the last space on this line (between * and /) to enable debugging. Don't forget to disable this when you're done! * /
  error_reporting(E_ALL);
  restore_error_handler();
  ini_set('display_errors', 1);

  /* Languages */
$__languages = array('bg', 'cz', 'de', 'en', 'es', 'fr', 'hu', 'id', 'it', 'ja', 'nl', 'pl', 'pt', 'ro', 'ru', 'sr', 'tr', 'uk', 'zh');

$__action = isset($_GET['action']) && $_GET['action'] !== '' ? $_GET['action'] : 'view';

if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '')
{
	$realPath = &$_SERVER['REQUEST_URI'];
}
elseif (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME'] !== '')
{
	$realPath = &$_SERVER['SCRIPT_NAME'];
}
else
{
	exit(LANG_UNKNOWN_DIR);
}
if (substr($realPath, -1) === '/')
{
	header('Location: '.$realPath.'index.php');
	exit;
}

$dirName = dirname($realPath);
if ($dirName === '/')
{
	$dirName = '';
}
define('CLICKHEAT_PATH', $dirName.'/');
define('CLICKHEAT_INDEX_PATH', 'index.php?');
define('CLICKHEAT_ROOT', str_replace('\\', '/', dirname(__FILE__)).'/');
define('CLICKHEAT_CONFIG', CLICKHEAT_ROOT.'config/config.php');
define('IS_PIWIK_MODULE', false);

if (function_exists('ob_start'))
{
	ob_start();
}

/* Loading language according to browser's Accept-Language or cookie «language» */
if (isset($_GET['language']))
{
	$lang = $_GET['language'];
}
elseif (isset($_COOKIE['language']))
{
	$lang = $_COOKIE['language'];
}
elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
{
	$lang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
}
if (!isset($lang) || !in_array($lang, $__languages))
{
	$lang = 'en';
}
if (!isset($_COOKIE['language']) || $_COOKIE['language'] !== $lang)
{
	setcookie('language', $lang, time() + 365 * 86400, '/');
}
define('CLICKHEAT_LANGUAGE', $lang);
unset($lang);
include CLICKHEAT_ROOT.'languages/'.CLICKHEAT_LANGUAGE.'.php';

/* If there's no config file, run check script */
if (!file_exists(CLICKHEAT_CONFIG))
{
	if ($__action !== 'check' && $__action !== 'config')
	{
		$__action = 'check';
	}
}
else
{
	include CLICKHEAT_CONFIG;

	if (isset($_COOKIE['clickheat']))
	{
		if ($_COOKIE['clickheat'] === $clickheatConf['adminLogin'].'||'.$clickheatConf['adminPass'])
		{
			/* Everything is fine, admin logged */
			define('CLICKHEAT_ADMIN', true);
		}
		elseif ($_COOKIE['clickheat'] === $clickheatConf['viewerLogin'].'||'.$clickheatConf['viewerPass'])
		{
			/* Viewer logged, force it to 'view' action if not view|generate|png */
			if ($__action !== 'generate' && $__action !== 'png' && $__action !== 'iframe' && $__action !== 'cleaner' && $__action !== 'logout')
			{
				$__action = 'view';
			}
		}
		else
		{
			/* Not logged, send him to login form */
			$__action = 'logout';
		}
	}
	else
	{
		if (isset($_POST['login']) && isset($_POST['pass']))
		{
			if ($_POST['login'] === $clickheatConf['adminLogin'] && md5($_POST['pass']) === $clickheatConf['adminPass'])
			{
				/* Set a session cookie */
				setcookie('clickheat', $clickheatConf['adminLogin'].'||'.$clickheatConf['adminPass'], 0, '/');
				/* Redirect to index.php */
				header('Content-Type: text/html');

				/* Upgrade needed ? */
				include CLICKHEAT_ROOT.'version.php';
				if (!isset($clickheatConf['version']) || $clickheatConf['version'] !== CLICKHEAT_VERSION)
				{
					$url = CLICKHEAT_INDEX_PATH.'action=config';
				}
				else
				{
					$url = CLICKHEAT_INDEX_PATH.'action=view';
				}
				/* IIS removes cookies when sending a 301/302 header, so we need to do some crap (and yes, this HTML code is crap too) */
				if (strpos($_SERVER['SERVER_SOFTWARE'], 'IIS'))
				{
					echo '<meta http-equiv="refresh" content="0;', $url, '" />';
					echo '<a href="', $url, '">click here</a>';
				}
				else
				{
					header('Location: '.$url);
				}
				exit;
			}
			elseif ($clickheatConf['viewerLogin'] !== '' && $_POST['login'] === $clickheatConf['viewerLogin'] && md5($_POST['pass']) === $clickheatConf['viewerPass'])
			{
				/* Set a session cookie */
				setcookie('clickheat', $clickheatConf['viewerLogin'].'||'.$clickheatConf['viewerPass'], 0, '/');
				/* Redirect to index.php */
				header('Content-Type: text/html');
				/* IIS removes cookies when sending a 301/302 header, so we need to do some crap (and yes, this HTML code is crap too) */
				if (strpos($_SERVER['SERVER_SOFTWARE'], 'IIS'))
				{
					echo '<meta http-equiv="refresh" content="0;', CLICKHEAT_INDEX_PATH, 'action=view" />';
					echo '<a href="', CLICKHEAT_INDEX_PATH, 'action=view">click here</a>';
				}
				else
				{
					header('Location: '.CLICKHEAT_INDEX_PATH.'action=view');
				}
				exit;
			}
		}
		$__action = 'login';
	}
}
if (!defined('CLICKHEAT_ADMIN'))
{
	define('CLICKHEAT_ADMIN', false);
}

/* Specific definitions */
$clickheatConf['__screenSizes'] = array(0 /* Must start with 0 */, 240, 640, 800, 1024, 1152, 1280, 1440, 1600, 1800);
$clickheatConf['__browsersList'] = array('all' => '', 'msie' => 'Internet Explorer', 'firefox' => 'Firefox', 'chrome' => 'Chrome', 'safari' => 'Safari', 'opera' => 'Opera', 'unknown' => '');

$checkHtml = false;

switch ($__action)
{
	case 'config':
		if (file_exists(CLICKHEAT_CONFIG) && CLICKHEAT_ADMIN !== true)
		{
			exit('Error');
		}
	/* No break here */
	case 'check':
	case 'view':
	case 'login':
		$checkHtml = true;
		header('Content-Type: text/html; charset=utf-8');
		include CLICKHEAT_ROOT.'header.php';
		include CLICKHEAT_ROOT.$__action.'.php';
		include CLICKHEAT_ROOT.'footer.php';
		break;

	case 'generate':
	case 'layout':
	case 'javascript':
	case 'latest':
	case 'cleaner':
		$checkHtml = true;
		header('Content-Type: text/html; charset=utf-8');
		include CLICKHEAT_ROOT.$__action.'.php';
		break;

	case 'iframe':
		$group = isset($_GET['group']) ? str_replace('/', '', $_GET['group']) : '';
		if (is_dir($clickheatConf['logPath'].$group))
		{
			$webPage = array('/');
			if (file_exists($clickheatConf['logPath'].$group.'/url.txt'))
			{
				$f = fopen($clickheatConf['logPath'].$group.'/url.txt', 'r');
				if ($f !== false)
				{
					$webPage = explode('>', trim(fgets($f, 1024)));
					fclose($f);
				}
			}
			echo $webPage[0];
		}
		break;

	case 'png':
		$imagePath = $clickheatConf['cachePath'].(isset($_GET['file']) ? str_replace('/', '', $_GET['file']) : '**unknown**');

		header('Content-Type: image/png');
		if (file_exists($imagePath))
		{
			readfile($imagePath);
		}
		else
		{
			readfile(CLICKHEAT_ROOT.'images/warning.png');
		}
		break;

	case 'layoutupdate':
		if (CLICKHEAT_ADMIN !== true)
		{
			exit('Error');
		}
		$group = isset($_GET['group']) ? str_replace('/', '', $_GET['group']) : '';
		$url = isset($_GET['url']) ? $_GET['url'] : '';
		if (strpos($url, 'http') !== 0)
		{
			$url = 'http://'.$_SERVER['SERVER_NAME'].'/'.ltrim($url, '/');
		}
		/* Improved security for PHP injection (PMV2.3b3 bug) */
		$url = parse_url(str_replace(array('<', '>'), array('', ''), $url));
		$left = isset($_GET['left']) ? (int) $_GET['left'] : 0;
		$center = isset($_GET['center']) ? (int) $_GET['center'] : 0;
		$right = isset($_GET['right']) ? (int) $_GET['right'] : 0;

		if (!is_dir($clickheatConf['logPath'].$group) || !isset($url['host']) || !isset($url['path']))
		{
			exit('Error');
		}

		if ($url['scheme'] !== 'http' && $url['scheme'] !== 'https')
		{
			$url['scheme'] = 'http';
		}
		if (isset($url['query']))
		{
			$url = $url['scheme'].'://'.$url['host'].$url['path'].'?'.$url['query'];
		}
		else
		{
			$url = $url['scheme'].'://'.$url['host'].$url['path'];
		}
		$f = fopen($clickheatConf['logPath'].$group.'/url.txt', 'w');
		fputs($f, $url.'>'.$left.'>'.$center.'>'.$right);
		fclose($f);

		echo 'OK';
		break;

	case 'logout':
		setcookie('clickheat', '', time() - 30 * 86400, '/');
		/* IIS removes cookies when sending a 301/302 header, so we need to do some crap (and yes, this HTML code is crap too) */
		if (strpos($_SERVER['SERVER_SOFTWARE'], 'IIS'))
		{
			echo '<meta http-equiv="refresh" content="0;', CLICKHEAT_INDEX_PATH, 'action=view" />';
			echo '<a href="', CLICKHEAT_INDEX_PATH, 'action=view">click here</a>';
		}
		else
		{
			header('Location: index.php');
		}
		exit;

	default:
		header('HTTP/1.0 404 Not Found');
		exit('Error, page not found');
		break;
}

if ($checkHtml === true && isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'clickheat.dugwood.com' && Configuration::dev === true)
{
	$content = ob_get_clean();
	if (strpos($content, '<!DOCTYPE') === 0 && strpos($content, '<!-- ignore-html-checker -->') === false && ($pos = strrpos($content, '</body>')) !== false)
	{
		$content = substr($content, 0, $pos).Html::getCode($content).substr($content, $pos);
	}
	echo $content;
}