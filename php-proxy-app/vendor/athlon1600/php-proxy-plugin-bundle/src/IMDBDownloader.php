<?php
namespace Proxy\Plugin;

//// utils.php

// YouTube is capitalized twice because that's how youtube itself does it:
// https://developers.google.com/youtube/v3/code_samples/php
class IMDBDownloader {
	
	private $storage_dir;
	private $cookie_dir;
	
	private $itag_info = array(
	
		18 => "MP4 360p",
		22 => "MP4 720p (HD)",
		37 => "MP4 1080",
		38 => "MP4 3072p",
		
		// questionable MP4s
		59 => "MP4 480p",
		78 => "MP4 480p",
	
		43 => "WebM 360p",
		
		17 => "3GP 144p"
	);
	
	function __construct(){
		$this->storage_dir = sys_get_temp_dir();
		$this->cookie_dir = sys_get_temp_dir();
	}
	
	function setStorageDir($dir){
		$this->storage_dir = $dir;
	}
	
	// what identifies each request? user agent, cookies...
	public function curl($url){
	
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
		//curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		
		//curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
	
	public static function head($url){
		
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		
		return http_parse_headers($result);
	}
	
	// html code of watch?v=aaa
	private function getInstructions($html){
		
		// <script src="//s.ytimg.com/yts/jsbin/player-fr_FR-vflHVjlC5/base.js" name="player/base"></script>
		
		// check what player version that video is using
		if(preg_match('@<script\s*src="([^"]+js)@', $html, $matches)){
			
			$player_url = $matches[1];
			
			// relative protocol?
			if(strpos($player_url, '//') === 0){
				$player_url = 'http://'.substr($player_url, 2);
			} else if(strpos($player_url, '/') === 0){
				// relative path?
				$player_url = 'http://www.imdb.com'.$player_url;
			}
			
			// try to find instructions list already cached from previous requests...
			$file_path = $this->storage_dir.'/'.md5($player_url);
			
			if(file_exists($file_path)){
				
				// unserialize could fail on empty file
				$str = file_get_contents($file_path);
				return unserialize($str);;
				
			} else {
				
				$js_code = $this->curl($player_url);
				$instructions = sig_js_decode($js_code);
				
				if($instructions){
					file_put_contents($file_path, serialize($instructions));
					return $instructions;
				}
			}
		}
		
		return false;
	}
	
	// this is in beta mode!!
	public function stream($id){
		
		$links = $this->getDownloadLinks($id, "mp4");
		
		if(count($links) == 0){
			die("no url found!");
		}
		
		$url = $links[0]['url'];
		
		$ch = curl_init();

		$headers = array();
		
		if(isset($_SERVER['HTTP_RANGE'])){
			$headers[] = 'Range: '.$_SERVER['HTTP_RANGE'];
		}
		
		$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0';
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		
		//curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096);
		curl_setopt($ch, CURLOPT_URL, $url);
		
		//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		
		// we deal with this ourselves
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		// whether request to video success
		$headers = '';
		$headers_sent = false;
		$success = false;
		
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $data) use (&$headers, &$headers_sent){
			
			$headers .= $data;
			
			// this should be first line
			if(preg_match('@HTTP\/\d\.\d\s(\d+)@', $data, $matches)){
				$status_code = $matches[1];
				
				if($status_code == 200 || $status_code == 206){
					$headers_sent = true;
					header(rtrim($data));
				}
				
			} else {
				
				// only headers we wish to forward back to the client
				$forward = array('content-type', 'content-length', 'accept-ranges', 'content-range');
				
				$parts = explode(':', $data, 2);
				
				if($headers_sent && count($parts) == 2 && in_array(trim(strtolower($parts[0])), $forward)){
					header(rtrim($data));
				}
			}
			
			return strlen($data);
		});
		
		
		// if response is empty - this never gets called
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$headers_sent){
			
			if($headers_sent){
				echo $data;
				flush();
			}
			
			return strlen($data);
		});
		
		$ret = @curl_exec($ch);
		curl_close($ch);
		
		// if we are still here by now, return status_code
		return true;
	}
	
	// extract youtube video_id from any piece of text
	public function extractId($str){
		
		if(preg_match('/[a-z0-9_-]{11}/i', $str, $matches)){
			return $matches[0];
		}
		
		return false;
	}
	
	// selector by format: mp4 360, 
	private function selectFirst($links, $selector){
		
		$result = array();
		$formats = preg_split('/\s*,\s*/', $selector);
		
		// has to be in this order
		foreach($formats as $f){
			
			foreach($links as $l){
				
				if(stripos($l['format'], $f) !== false || $f == 'any'){
					$result[] = $l;
				}
			}
		}
		
		return $result;
	}
	
	// options | deep_links | append_redirector
	public function getDownloadLinks($id, $selector = false){
		
		$result = array();
		$instructions = array();
		
//		console_log('IMDB getDownloadLinks: '. $id);

        $html = $this->curl($id);
//        console_log('imdbdownloader see html: '. $html);


//        <video class="jw-video jw-reset" tabindex="-1" disableremoteplayback="" webkit-playsinline="" playsinline="" src="https://cdn-a.amazon-adsystem.com/video/1e2ce81c-51f4-4ef3-9b9f-8ae601bb3578/10000-1920x1080.mp4" volume="0.9"></video>
        if(preg_match('@"videoUrl":["\']([^"\'\s]*)@', $html, $matches)){
//            \u002F
//            console_log('see imdb url before: '. $matches[1]);
            $matches[1] = str_replace('\\u002F','/',   $matches[1]);
//            console_log('see imdb url: '. $matches[1]);
            $result['url'] = $matches[1];
        }

		// http://stackoverflow.com/questions/35608686/how-can-i-get-the-actual-video-url-of-a-youtube-live-stream
//		if(preg_match('@url_encoded_fmt_stream_map["\']:\s*["\']([^"\'\s]*)@', $html, $matches)){
//
//			$parts = explode(",", $matches[1]);
//
//			foreach($parts as $p){
//				$query = str_replace('\u0026', '&', $p);
//				parse_str($query, $arr);
//
//				$url = $arr['url'];
//
//				if(isset($arr['sig'])){
//					$url = $url.'&signature='.$arr['sig'];
//
//				} else if(isset($arr['signature'])){
//					$url = $url.'&signature='.$arr['signature'];
//
//				} else if(isset($arr['s'])){
//
//					// this is probably a VEVO/ads video... signature must be decrypted first! We need instructions for doing that
//					if(count($instructions) == 0){
//						$instructions = (array)$this->getInstructions($html);
//					}
//
//					$dec = $this->sig_decipher($arr['s'], $instructions);
//					$url = $url.'&signature='.$dec;
//				}
//
//				// redirector.googlevideo.com
//				//$url = preg_replace('@(\/\/)[^\.]+(\.googlevideo\.com)@', '$1redirector$2', $url);
//
//				$itag = $arr['itag'];
//				$format = isset($this->itag_info[$itag]) ? $this->itag_info[$itag] : 'Unknown';
//
//				$result[$itag] = array(
//					'url' => $url,
//					'format' => $format
//				);
//			}
//		}

//		// do we want all links or just select few?
//		if($selector){
//			return $this->selectFirst($result, $selector);
//		}
		
		return $result;
	}
	
	private function sig_decipher($signature, $instructions){
		
		foreach($instructions as $opt){
			
			$command = $opt[0];
			$value = $opt[1];
			
			if($command == 'swap'){
				
				$temp = $signature[0];
				$signature[0] = $signature[$value % strlen($signature)];
				$signature[$value] = $temp;
				
			} else if($command == 'splice'){
				$signature = substr($signature, $value);
			} else if($command == 'reverse'){
				$signature = strrev($signature);
			}
		}
		
		return trim($signature);
	}
}


?>