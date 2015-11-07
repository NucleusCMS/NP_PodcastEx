<?
/*
 * NP_PodcastEx - provide support for podcasting in Nucleus
 * 
 * Usage:
 * 	1) install the plugin
 * 	2) modify the feeds/rss20 template and add <%Podcast%> inside the item tag after
 * 	<pubDate>...</pubDate>
 * 	3) upload the mp3 and use the skinvar <%PodcastEx(id|text|width|height)%>. For offshore media file that
 * 	stored elsewhere, put in the URL directly
 * 
 * note:
 * 		default player is
 * 			Windows	:WindowsMediaPlayer
 * 			MacOS	:QuickTime
 * 		An usable media file depends on the environment of the user.
 * 		When you want to avoid this problem, please use the format which anyone can use in common.
 * 			e.g. mp3, mpeg(video)
 * hint:
 * 		It may be good to exhibit a necessary CODEC
 * 
 * Known issue:
 * 		- only one podcast in per post is assumed
 * 
 * history original NP_Podcast http://wakka.xiffy.nl/podcast
 * v0.1
 * 		- Initialize release
 * v0.2 Nov 4, 2004
 * 		- <%Podcast%> skinvar
 * v0.3 Apr 14, 2005
 * 		- add supportsFeature
 * 		- support to torrent and mp3
 * 		- audio.weblogs.com ping
 * 		- able to point enclosure offshore
 * v0.4 May 6, 2005
 * 		- fix ping...
 * 		- option to enable/disable ping
 * 
 * history extend version by MCI_Error
 * 
 * v0.5 Jun 28, 2009
 * 		- detect operation system at User-Agent
 */
// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table'))
{
	function sql_table($name)
	{
		return 'nucleus_' . $name;
	}
}

define('PODCAST_MARKER', '<!--PodcastEx-->');
 
class NP_PodcastEx extends NucleusPlugin
{
	var $authorid;
	var $security_check = false;
	var $agent_os;
	
	function getEventList() { return array('PreItem', 'PreAddItem', 'PostAddItem','PostAuthentication'); }
	function getName() { return 'PodcastEx'; }
	function getAuthor() { return 'MCI_Error / original by Edmond Hui (admun)'; }
	function getURL() { return "http://mcierror.axisz.jp/home/"; }
	function getVersion() { return '0.5'; }
	function getDescription() { return 'This plugin provides podcasting support in Nucleus via a new <%Podcast(file|comment%> template var'; }
	/*
	 *	Note: I never run this plugin on 2.0 and have no idea whether it
	 *	wil work on <2.5. A user can simply chnage it to return
	 *	'200' and see if it works (likely will). I will gladly
	 *	change the min version to 2.0 and add the sql_table fix
	 *	upon such report. 8)
	 */
	function getMinNucleusVersion() { return '341'; }
	function supportsFeature($what)
	{
		switch($what)
		{
			case 'SqlTablePrefix': return 1;
			default: return 0;
		}
	}
	function install()
	{
		$this->createOption('ping','Enable audio.weblogs.com ping and mcierror.axisz.jp ping','yesno','yes');
		$this->createOption('width','Player Default Width', 'text', '512');
		$this->createOption('height',"Player Default Height without Control Area.", 'text', '0');
	}
	function doAction($actionType)
	{
/*
		if ($actionType != 'captcha')
			return 'invalid action';
			
		// initialize on first call
		if (!$this->inited)
			$this->init_captcha();
			
		$key 	= getVar('key');
		$width	= intGetVar('width');
		$height	= intGetVar('height');

		if ($width < 200) $width = -1;
		if ($height < 25) $height = -1;
		
		$this->generateImage($key, $width, $height);
*/
	}
	
	function event_PostAuthentication($data)
	{
		$env = getenv('HTTP_USER_AGENT');
		if (preg_match("/(Linux|Windows|Mac)/i", $env, $matches))
		{
			$this->agent_os = strtolower($matches[1]);
		}else{
			$this->agent_os = 'unknown';
		}
	}
	// This function generates the actual URL to the podcast
	function event_PreItem($data)
	{
		global $item;
		$item = &$data["item"];
		$this->authorid = $item->authorid;
		if (strstr($item->body . " " . $item->more, "<%PodcastEx("))
		{
			$item->body = preg_replace_callback("#<\%PodcastEx\((.*?)\|([^)]*?)\)%\>#", array(&$this, 'replaceCallback'), $item->body);
			$item->mmore = preg_replace_callback("#<\%PodcastEx\((.*?)\|([^)]*?)\)%\>#", array(&$this, 'replaceCallback'), $item->more);
		}
	}
	
	function replaceCallback($matches)
	{
		global $CONF;
		$file = $matches[1];
		if ($matches[2] == '')
		{
			$text = $matches[1];
			$width = $this->getOption('width');
			$height = $this->getOption('height');
		}else{
			$args = explode('|', $matches[2]);
			if($args[0] == '')
			{
				$test = $file;
			}else{
				$text = $args[2];
			}
			if ($args[1] == '')
			{
				$width = $this->getOption('width');
			}else{
				$width = $args[1];
			}
			if ($args[2] == '')
			{
				$height = $this->getOption('height');
			}else{
				$height = $args[2];
			}
		}
		if (!strstr($file, "http:")) $file = $CONF['MediaURL'] . $this->authorid . "/" .  $file;
		
		// if user's os has a default media player, return tag <object...></object>
		switch ($this->agent_os)
		{
			case 'windows' : // call Windows media Player
				// wmp tool bar height = 98
				$height += 98;
				return PODCAST_MARKER.'<div class="podcast"><OBJECT CLASSID="CLSID:22D6F312-B0F6-11D0-94AB-0080C74C7E95" width="'.$width.'" height="'.$height.'">'
					.'<PARAM NAME="FileName" VALUE="'.$file.'" /><param name="ShowDisplay" value="true"><param name="AutoStart" value="false" />
					</OBJECT></div>';
			case 'mac' : // call quick time
				// qt tool bar height = 16
				$height += 16;
				return PODCAST_MARKER.'<div class="podcast"><object name="QT" classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" width="'.$width.'" height="'.$height.'">'
					.'<param name="src" value="'.$file.'" /></object></div>';
			default :
				return PODCAST_MARKER."<div class=\"podcast\"><a href=\"" . $file . "\">" . $text . "</a></div>";
		}
	}
	
	// This function generates the enclosure in RSS feed
	function doTemplateVar(&$item)
	{
		global $DIR_MEDIA, $CONF;
		// see if there is a podcast file here
		if (strstr($item->body." ".$item->more, PODCAST_MARKER))
		{
			$mem = MEMBER::createFromName($item->author);
			$id = $mem->getId();
			$search = "/\"(http:\/\/.*?\.(mp(e?g|2|3|4)|wm[adfvxz]|torrent))\"/i";
			preg_match($search, $item->body." ".$item->more, $result);
			$mfile = explode("/", $result[1]);
			$file = $DIR_MEDIA . $id . '/' . $mfile[sizeof($mfile)-1];
			if(file_exists($file))
			{
				$size = filesize($file);
			}else{
				$hdrs = array_change_key_case(get_headers($result[1],1),CASE_LOWER);
				$size = isset($hdrs['content-length']) ? $hdrs['content-length'] : 0;
			}
			$type = $this->get_contenttype($result[1]);
			$url = $result[1];
			echo "<enclosure url=\"$url\" length=\"$size\" type=\"$type\"/>";
		}
	}
		
	function event_PreAddItem($data)
	{
		$this->myBlogId    = $data['blog']->blogid;
		$this->draft = "no";
		$this->podcast = false;
		
		if (strstr($data['more'] . " " . $data['body'], "<%Podcast("))
		{
			$this->podcast = true;
		}
		if ($data['draft'] == '1')
		{
			$this->draft = "yes";
		}
	}
	
	function event_PostAddItem($data)
	{
		if ($this->draft == "no" && $this->podcast == true && $this->getOption('ping') == "yes")
		{
			$b = new BLOG($this->myBlogId);
			if (!class_exists('xmlrpcmsg'))
			{
				global $DIR_LIBS;
				include($DIR_LIBS . 'xmlrpc.inc.php');
			}
			$message = new xmlrpcmsg(
				'weblogUpdates.ping', array(
					new xmlrpcval($b->getName(),'string'),
					new xmlrpcval($b->getURL(),'string'),
				));
			$c = new xmlrpc_client('/RPC2', 'audiorpc.weblogs.com', 80);
			//$c->setDebug(1);
			$r = $c->send($message,15); // 15 seconds timeout...
			$m = new xmlrpc_client('/pingserver.php', 'mcierror.axisz.jp', 80);
			$r = $m->send($message, 15);

		}
	}
	function get_contenttype($filename)
	{
/*
		if (function_exists('mime_content_type')) return mime_content_type($filename);
		if (function_exists('finfo_file'))
		{
			$f = finfo_open(FILEINFO_MIME);
			$r = finfo_file($f, $filename, FILEINFO_MIME);
			finfo_close($f);
			return $r;
		}
*/
		$data = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		switch ($data)
		{
			case 'mp2' :
			case 'mp3' : return 'audio/mpeg';
			case 'wma' : return 'audio/x-ms-wma';
			case 'mp4' : return 'video/mp4';
			case 'ogg' : return 'application/ogg';
			case 'ra'  :
			case 'ram' : return 'audio/x-pn-realaudio';
			case 'mpa' :
			case 'mpe' :
			case 'mpeg':
			case 'mpg' :
			case 'mpv2': return 'video/mpeg';
			case 'asf' :
			case 'asr' :
			case 'asx' : return 'video/x-ms-asf';
			case 'wax' : return 'audio/x-ms-wax';
			case 'wm'  : return 'video/x-ms-wm';
			case 'wmd' : return 'application/x-ms-wmd';
			case 'wmf' : return 'application/x-msmetafile';
			case 'wmv' : return 'video/x-ms-wmv';
			case 'wmx' : return 'video/x-ms-wmx';
			case 'wmz' : return 'application/x-ms-wmz';
			case 'wvx' : return 'video/x-ms-wvx';
			case 'aif' : return 'audio/x-aiff';
			case 'aifc': return 'audio/x-aiff';
			case 'aiff': return 'audio/x-aiff';
			case 'au'  : return 'audio/basic';
			case 'avi' : return 'video/x-msvideo';
			case 'lsf' : return 'video/x-la-asf';
			case 'lsx' : return 'video/x-la-asf';
			case 'm3u' : return 'audio/x-mpegurl';
			case 'mid' : return 'audio/mid';
			case 'mov' : return 'video/quicktime';
			case 'movie': return 'video/x-sgi-movie';
			case 'qt'  : return 'video/quicktime';
			case 'rmi' : return 'audio/mid';
			case 'snd' : return 'audio/basic';
			case 'wav' : return 'audio/x-wav';
			case 'torrent' :return "application/x-bittorrent";
			default	: return "";
		}
	}
}
?>