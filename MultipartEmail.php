<?php

/**
 * PHP Multipart Mailer
 * @author Rebel http://www.aleksandr.ru
 * @version 1.0   18.04.2012
 * @version 1.0.1 01.10.2013 ('\r\n' replaced with '\n' when building message)
 * @version 1.0.2 06.03.2015 ($add_cid parameter added, html parsing for cid src, 'multipart/related' header in case of embedded images)
 * @version 1.0.3 22.09.2015 (added $charset parameter to constructor for non utf8 usage)
 * @version 1.0.4 25.12.2017 (added CSS "url('image.ext')" replacement with attachement CID)
 * @version 1.0.5 20.03.2020 (added backtrace and optional exception for send errors)
 * @version 1.0.6 23.07.2020 (image CID fix when send multiple times)
 */
class MultipartEmail
{
	protected $charset = 'UTF-8';
	protected $text;
	protected $html;
	protected $from;
	protected $to;
	protected $subject;
	protected $attachements = array();
	protected $headers = array();

	private $att_counter = 0;
	private $hdr_counter = 0;
	
	/**
	* init. set charset for all data (from, to, subject, text and html)
	* @param string $charset for non utf8 usage
	* @see iconv
	* 
	* @return void
	*/
	function __construct($charset = 'UTF-8')
	{
		$this->charset = $charset;
	}

	/**
	* set the plain text of email
	* @param string $val
	* 
	* @return void
	*/
	function setText($val)
	{
		$this->text = $val;
	}

	/**
	* get plain text part of email
	* 
	* @return string
	*/
	function getText()
	{
		return $this->text;
	}

	/**
	* set html part of email
	* @param string $val
	* 
	* @return void
	*/
	function setHtml($val)
	{
		$this->html = $val;
	}
	
	/**
	* get html part of email
	* 
	* @return string
	*/
	function getHtml()
	{
		return $this->html;
	}

	/**
	* set TO address ('some@mail.com' or 'some name <some@mail.com>')
	* multi addresse can be divided by , or ;
	* @param string $val
	* 
	* @return void
	*/
	function setTo($val)
	{
		$this->to = $val;
	}
	
	/**
	* get TO address
	* 
	* @return string
	*/
	function getTo()
	{
		return $this->to;
	}
	
	/**
	* set FROM address ('some@mail.com' or 'some name <some@mail.com>')
	* @param string $val
	* 
	* @return void
	*/
	function setFrom($val)
	{
		$this->from = $val;
	}
	
	/**
	* get FROM address
	* 
	* @return string
	*/
	function getFrom()
	{
		return $this->from;
	}
	
	/**
	* set email SUBJECT
	* @param string $val
	* 
	* @return void
	*/
	function setSubject($val)
	{
		$this->subject = $val;
	}
	
	/**
	* get email SUBJECT
	* 
	* @return string
	*/
	function getSubject()
	{
		return $this->subject;
	}
	
	/**
	* add attachement to email
	* @param string $file filename or data of attachement
	* @param string $mime_type
	* @param string $name filename of attachement
	* @param bool $file_is_data if TRUE use $file as data, if FALSE load data from $file
	* @param bool $add_cid add content-id to embed in html part by parsing src='...' attribute
	* 
	* @return integer attachement id (index)
	*/
	function addAttachement($file, $mime_type, $name, $file_is_data = false, $add_cid = false)
	{
		$this->att_counter++;
		$this->attachements[$this->att_counter]['data'] = $file_is_data ? $file : file_get_contents($file);
		$this->attachements[$this->att_counter]['mime_type'] = $mime_type;
		$this->attachements[$this->att_counter]['name'] = $name;
		$this->attachements[$this->att_counter]['cid'] = $add_cid ? uniqid('MPM-cid-') : null;
		if(!$this->attachements[$this->att_counter]['data'] || !$this->attachements[$this->att_counter]['mime_type'] || !$this->attachements[$this->att_counter]['name']) {
			unset($this->attachements[$this->att_counter]);
			trigger_error("Failed to add an attachement!", E_USER_WARNING);
			return false;
		}
		return $this->att_counter;
	}
	
	/**
	* remove attachement 
	* @param integer $id index of the attachement
	* 
	* @return bool
	*/
	function removeAttachement($id)
	{
		if(isset($this->attachements[$id])) {
			unset($this->attachements[$id]);
			return true;
		} else {
			trigger_error("Attachement not found");
			return false;
		}
	}
	
	/**
	* add an extra header to email ('Header-name: header-value')
	* @param string $header
	* 
	* @return integer header id (index)
	*/
	function addHeader($header)
	{
		$this->hdr_counter++;
		if(!$header) {
			trigger_error("Can't add empty header!", E_USER_WARNING);
			return false;
		}
		$this->headers[$this->hdr_counter] = $header;		
		return $this->hdr_counter;
	}
	
	/**
	* remove an extra header
	* @param integer $id index of the header
	* 
	* @return bool
	*/
	function removeHeader($id)
	{
		if(isset($this->headers[$id])) {
			unset($this->headers[$id]);
			return true;
		} else {
			trigger_error("Header not found");
			return false;
		}
	}
	
	/**
	 * send mail
     * @param bool $throw_exception in case of empty TO
	 *
	 * @return bool
     * @throws Exception
	 */
	function send($throw_exception = false)
	{
		if(!$this->to) {
            $e = new Exception("Send failed, TO is empty!");
            if($throw_exception) {
                throw $e;
            }
			trigger_error($e->getMessage() . " Trace:\n" . $e->getTraceAsString(), E_USER_WARNING);
			return false;
		}

        $html = $this->html;

		$multipart = ($html || sizeof($this->attachements));
		$related   = false; // default i.e. no embedded images in html
		
		$boundary_part = uniqid('MPM-part-');
		$boundary_alt = uniqid('MPM-alt-');

        if($html && sizeof($this->attachements)) {
			foreach($this->attachements as $i => $a) if($a['cid'] && preg_match("/^image/", $a['mime_type'])) {
				$html = preg_replace("/src=(['\"]?)".addslashes($this->attachements[$i]['name'])."(['\"]?)/i",
										   "src=$1cid:{$this->attachements[$i]['cid']}$2", $html);
				$html = preg_replace("/url\((['\"]?)".addslashes($this->attachements[$i]['name'])."(['\"]?)\)/i",
										   "url(cid:{$this->attachements[$i]['cid']})", $html);
				$related = true;
			}
		}
		
		/* headers */
		
		$email_regexp = "/^(.+) <(.+\@.+)>$/i";
		if(preg_match($email_regexp, trim($this->from), $arr)) {
			$from = "=?UTF-8?B?".base64_encode($this->toUTF8($arr[1]))."?= <{$arr[2]}>";
		} else {
			$from = $this->from;
		}
		$to = preg_split("/[,;]/", $this->to);
		foreach($to as $i=>$t) if(preg_match($email_regexp, trim($t), $arr)) {
			$to[$i] = "=?UTF-8?B?".base64_encode($this->toUTF8($arr[1]))."?= <{$arr[2]}>";
		} else {
			$to[$i] = $t;
		}
		$to = implode(", ", $to);

		$subject = "=?UTF-8?B?".base64_encode($this->toUTF8($this->subject))."?=";
		
		$headers[] = "From: {$from}";
		$headers[] = "Reply-To: {$from}"; 
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "X-Mailer: PHP/MPM 1.0 by Rebel";		
	
		if($multipart && !$related) $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary_part\"";
		elseif($multipart && $related) $headers[] = "Content-Type: multipart/related; boundary=\"$boundary_part\"";
		else $headers[] = "Content-Type: text/plain; charset={$this->charset}\nContent-Transfer-Encoding: base64";
		
		if(sizeof($this->headers)) $headers = array_merge($headers, $this->headers);
		$headers = implode("\n", $headers);

		$message = "";

		/* text/alternative part */

		if(!$multipart) {
			$message .= chunk_split(base64_encode($this->text))."\n";
		} elseif($this->text && $html) {
			$message .= "--$boundary_part\n";
			$message .= "Content-Type: multipart/alternative; boundary=\"$boundary_alt\"\n\n";

			$message .= "--$boundary_alt\n";
			$message .= "Content-Type: text/plain; charset={$this->charset}\nContent-Transfer-Encoding: base64\n\n";
			$message .= chunk_split(base64_encode($this->text))."\n";
			$message .= "--$boundary_alt\n";
			$message .= "Content-Type: text/html; charset={$this->charset}\nContent-Transfer-Encoding: base64\n\n";
			$message .= chunk_split(base64_encode($html))."\n";
			$message .= "--$boundary_alt--\n\n";
		} elseif($this->text) {
			$message .= "--$boundary_part\n";
			$message .= "Content-Type: text/plain; charset={$this->charset}\nContent-Transfer-Encoding: base64\n\n";
			$message .= chunk_split(base64_encode($this->text))."\n";
		} elseif($html) {
			$message .= "--$boundary_part\n";
			$message .= "Content-Type: text/html; charset={$this->charset}\nContent-Transfer-Encoding: base64\n\n";
			$message .= chunk_split(base64_encode($html))."\n";
		}
		
		/* attachements */

		foreach($this->attachements as $att) {
			$att['name'] = "=?UTF-8?B?".base64_encode($this->toUTF8($att['name']))."?=";

			$message .= "--$boundary_part\n";
			if($att['cid']) $message .= "Content-ID: <{$att['cid']}>\n";
			$message .= "Content-Type: {$att['mime_type']}; name=\"{$att['name']}\"\n";
			$message .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\n";
			$message .= "Content-Transfer-Encoding: base64\n\n";			
			$message .= chunk_split(base64_encode($att['data']))."\n";
		} 
		
		if($multipart) $message .= "--$boundary_part--\n\n";

		return mail($to, $subject, $message, $headers);
	}
	
	/**
	* convert string to utf-8
	* @param string $str
	* 
	* @return string
	*/
	protected function toUTF8($str)
	{
		if(strtoupper($this->charset) == 'UTF-8') return $str;		
		else return iconv($this->charset, 'UTF-8', $str);
	}
}
