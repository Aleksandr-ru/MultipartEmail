<?php

/**
 * PHP Multipart Mailer
 * @author Aleksandr.ru
 * @url http://aleksandr.ru
 * @version 1.0   18.04.2012
 * @version 1.0.1 01.10.2013 ('\r\n' replaced with '\n' when building message)
 * @version 1.0.2 06.03.2015 ($add_cid parameter added, html parsing for cid src, 'multipart/related' header in case of embedded images)
 * @version 1.0.3 22.09.2015 (added $charset parameter to constructor for non utf8 usage)
 * @version 1.0.4 25.12.2017 (added CSS "url('image.ext')" replacement with attachment CID)
 * @version 1.0.5 20.03.2020 (added backtrace and optional exception for send errors)
 * @version 1.0.6 23.07.2020 (image CID fix when send multiple times)
 * @version 1.0.7 02.10.2020 (composer support)
 * @version 1.1   09.09.2022 (named headers and reply-to feature)
 * @version 1.1.1 29.02.2023 (email regexp allows absence of space between name and address)
 */
class MultipartEmail
{
    const EMAIL_REGEXP = "/^(.+)\s*<(.+\@.+)>$/i";

	protected $charset = 'UTF-8';
	protected $text;
	protected $html;
	protected $from;
	protected $to;
    protected $reply_to;
	protected $subject;
	protected $attachments = array();
	protected $headers = array();

	private $att_counter = 0;
	
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
	* multi addresses can be divided by , or ;
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
     * set REPLY-TO address ('some@mail.com' or 'some name <some@mail.com>')
     * @param string $val
     *
     * @return void
     */
    function setReplyTo($val)
    {
        $this->reply_to = $val;
    }

    /**
     * get REPLY-TO address
     *
     * @return string
     */
    function getReplyTo()
    {
        return $this->reply_to;
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
	* add attachment to email
	* @param string $file filename or data of attachment
	* @param string $mime_type
	* @param string $name filename of attachment
	* @param bool $file_is_data if TRUE use $file as data, if FALSE load data from $file
	* @param bool $add_cid add content-id to embed in html part by parsing src='...' attribute
	* 
	* @return integer attachment id (index)
	*/
	function addAttachment($file, $mime_type, $name, $file_is_data = false, $add_cid = false)
	{
		$this->att_counter++;
		$this->attachments[$this->att_counter]['data'] = $file_is_data ? $file : file_get_contents($file);
		$this->attachments[$this->att_counter]['mime_type'] = $mime_type;
		$this->attachments[$this->att_counter]['name'] = $name;
		$this->attachments[$this->att_counter]['cid'] = $add_cid ? uniqid('MPM-cid-') : null;
		if(!$this->attachments[$this->att_counter]['data'] || !$this->attachments[$this->att_counter]['mime_type'] || !$this->attachments[$this->att_counter]['name']) {
			unset($this->attachments[$this->att_counter]);
			trigger_error("Failed to add an attachment!", E_USER_WARNING);
			return false;
		}
		return $this->att_counter;
	}
	
	/**
	* remove attachment
	* @param integer $id index of the attachment
	* 
	* @return bool
	*/
    function removeAttachment($id)
	{
		if(isset($this->attachments[$id])) {
			unset($this->attachments[$id]);
			return true;
		} else {
			trigger_error("Attachment not found");
			return false;
		}
	}

    /**
     * @deprecated backwards compatibility
     */
    function addAttachement($file, $mime_type, $name, $file_is_data = false, $add_cid = false)
    {
       return $this->addAttachment($file, $mime_type, $name, $file_is_data, $add_cid);
    }

    /**
     * @deprecated backwards compatibility
     */
    function removeAttachement($id)
    {
        return $this->removeAttachment($id);
    }
	
	/**
	* add extra header to email ('Header-name: header-value')
    * caution! any message header can be redefined with this
	* @param string $header
	* 
	* @return string header-name
	*/
	function addHeader($header)
	{
		if(!preg_match('/^(.+):.+/', $header, $matches)) {
			trigger_error(sprintf('Invalid header "%s"', $header), E_USER_WARNING);
			return false;
		}
        $key = strtolower($matches[1]);
		$this->headers[$key] = $header;
		return $key;
	}

    /**
     * get extra header value
     * @param string $key header-name
     *
     * @return string
     */
    function getHeader($key)
    {
        $key = strtolower($key);
        if(isset($this->headers[$key])) {
            return $this->headers[$key];
        } else {
            trigger_error(sprintf('Header "%s" not found', $key));
            return false;
        }
    }
	
	/**
	* remove an extra header
	* @param string $key header-name
	* 
	* @return bool
	*/
	function removeHeader($key)
	{
        $key = strtolower($key);
		if(isset($this->headers[$key])) {
			unset($this->headers[$key]);
			return true;
		} else {
			trigger_error(sprintf('Header "%s" not found', $key));
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

		$multipart = ($html || sizeof($this->attachments));
		$related   = false; // default i.e. no embedded images in html
		
		$boundary_part = uniqid('MPM-part-');
		$boundary_alt = uniqid('MPM-alt-');

        if($html && sizeof($this->attachments)) {
			foreach($this->attachments as $i => $a) if($a['cid'] && preg_match("/^image/", $a['mime_type'])) {
				$html = preg_replace("/src=(['\"]?)".addslashes($this->attachments[$i]['name'])."(['\"]?)/i",
										   "src=$1cid:{$this->attachments[$i]['cid']}$2", $html);
				$html = preg_replace("/url\((['\"]?)".addslashes($this->attachments[$i]['name'])."(['\"]?)\)/i",
										   "url(cid:{$this->attachments[$i]['cid']})", $html);
				$related = true;
			}
		}
		
		/* headers */

		if(preg_match(self::EMAIL_REGEXP, trim($this->from), $arr)) {
			$from = "=?UTF-8?B?".base64_encode($this->toUTF8($arr[1]))."?= <{$arr[2]}>";
		} else {
			$from = $this->from;
		}

        if(preg_match(self::EMAIL_REGEXP, trim($this->reply_to), $arr)) {
            $reply_to = "=?UTF-8?B?".base64_encode($this->toUTF8($arr[1]))."?= <{$arr[2]}>";
        } elseif($this->reply_to) {
            $reply_to = $this->reply_to;
        }
        else {
            $reply_to = $from;
        }

		$to = preg_split("/[,;]/", $this->to);
		foreach($to as $i=>$t) if(preg_match(self::EMAIL_REGEXP, trim($t), $arr)) {
			$to[$i] = "=?UTF-8?B?".base64_encode($this->toUTF8($arr[1]))."?= <{$arr[2]}>";
		} else {
			$to[$i] = $t;
		}
		$to = implode(", ", $to);

		$subject = "=?UTF-8?B?".base64_encode($this->toUTF8($this->subject))."?=";
		
		$headers['from']         = "From: {$from}";
		$headers['reply-to']     = "Reply-To: {$reply_to}";
		$headers['mime-version'] = "MIME-Version: 1.0";
		$headers['x-mailer']     = "X-Mailer: PHP/MPM 1.1 by aleksandr.ru";
	
		if($multipart && !$related) {
            $headers['content-type'] = "Content-Type: multipart/mixed; boundary=\"$boundary_part\"";
        } elseif($multipart && $related) {
            $headers['content-type'] = "Content-Type: multipart/related; boundary=\"$boundary_part\"";
        } else {
            $headers['content-type'] = "Content-Type: text/plain; charset={$this->charset}\nContent-Transfer-Encoding: base64";
        }
		
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
		
		/* attachments */

		foreach($this->attachments as $att) {
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
