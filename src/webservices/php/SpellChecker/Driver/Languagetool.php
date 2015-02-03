<?php
/**
 * LanguageTool driver for jQuery Spellchecker (https://github.com/badsyntax/jquery-spellchecker)
 * !! Curl is required to use the languagetool spellchecker API !!
 *
 * @author     Sebastian Hodapp
 * @copyright  (c) Sebastian Hodapp, http://www.sebastian-hodapp.de
 * @license    https://github.com/badsyntax/jquery-spellchecker/blob/master/LICENSE-MIT
 * 
 * Start a LanguageTool instance (http://www.languagetool.org/) on a server of your choice.
 * Driver's parameters:
 * - lang: need to be in line with LanguageTool (e.g. en-US)
 * - url:  LanguageTool-Server-IP, if not on same host
 * - port: LanguageTool-Server-Port
 * - disabled: disabled LanguageTool-rules. Use comma seperation if more than one rule should be disabled.
 */

namespace SpellChecker\Driver;

class Languagetool extends \SpellChecker\Driver
{
	protected $_default_config = array(
		'lang' => 'en-US',
		'url'  => 'localhost',
		'port' => 8081,
		'disabled' => 'UPPERCASE_SENTENCE_START'	
	);
	
	public function get_proofreading($text)
	{
		$parameters = array(
				'language' => $this->_config['lang'], 
				'text' => html_entity_decode($text, ENT_COMPAT, "UTF-8"),
				'disabled' => $this->_config['disabled']
		);
		
		foreach($parameters as $key=>$value) { $data .= $key.'='.$value.'&'; }
		rtrim($data, '&');
		
		if (!function_exists('curl_init'))
		{
			exit('Curl is not available');
		}
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL,  $this->_config['url']);
		curl_setopt($curl, CURLOPT_PORT, $this->_config['port']);
		
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		
		$xml_response = curl_exec($curl);
		
		if ($xml_response == FALSE){
			exit('Curl request ended with error: ' . curl_error($curl));
		}
		
		$result = simplexml_load_string($xml_response);
	
		return $result;
	}

	public function get_word_suggestions($word = NULL)
	{
		$suggestions = array();

		foreach($this->get_proofreading($word)->error as $error)
		{
			$results 		= $error->attributes();	
			$replacements 	= explode("#", (string)$results["replacements"]);
			
			$suggestions = array_merge($suggestions, $replacements);
		}

		return $suggestions;
	}

	public function get_incorrect_words()
	{
		if (is_array(\SpellChecker\Request::post('text'))){
			$texts = (array) \SpellChecker\Request::post('text');
		} else {
			$texts = array(\SpellChecker\Request::post('text'));
		}
		$result = array();
		
		print_r($texts);
		
		foreach($texts as $text)
		{
			foreach($this->get_proofreading($text)->error as $error)
			{
				$results = $error->attributes();
				$word =  substr ((string)$results["context"] , (integer)$results["contextoffset"],(integer)$results["errorlength"]);
				array_push($result, $word);
			}
		}
		
		$this->send_data('success', Array($result));
	}

	public function check_word($word = NULL) {}

}
