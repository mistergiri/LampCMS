<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Forms;

use Lampcms\Request;
use Lampcms\Registry;
use Lampcms\LampcmsObject;

/**
 * This is base class for various web forms
 * It is responsible for rendering a form
 * using a tpl... template file, possibly
 * setting error message in the form,
 * It may also translate element titles and captions
 * as well as error messages.
 *
 * @author Dmitri Snytkine
 *
 */
abstract class Form extends LampcmsObject
{

	/**
	 * Use CSRF token
	 * by default it will set value of token
	 * in new form and will automatically
	 * validate value of submitted token
	 * if form is submitted (Request was POST)
	 *
	 * @var bool
	 */
	protected $useToken = true;

	/**
	 * Array of field names used in current form
	 * This should be set in sub-class that represents
	 * concrete form
	 *
	 * This is optional and if set, then
	 * keys are field names, values are... not sure yet,
	 * could be objects or arrays containing validator
	 * callback functions
	 *
	 * It is helpful to know which field names we have
	 * or going to have in the form.
	 *
	 * First it can help if we need to pre-populate
	 * field values in case of error during validation,
	 * we can just get already submitted values from Request
	 *
	 * @var array
	 */
	protected $aFields = array();

	/**
	 * Array of validator callback functions
	 * keys are field names, values are anonymous functions
	 * that take field name as param
	 *
	 * @var array
	 */
	protected $aValidators = array();

	/**
	 * Name of form template file
	 * The name of actual template should be
	 * set in sub-class
	 *
	 * @var string
	 */
	protected $template;

	/**
	 * Array of template vars
	 * This is usually a copy from template
	 * we get it via tplXXXX::getVars()
	 * then we can work with it like
	 * translating field values,
	 * pre-populating fields if form has already been
	 * submitted but contains errors in which case we want
	 * to show user errors but also preserve already
	 * submitted data in the form
	 *
	 * @var array
	 */
	protected $aVars;

	/**
	 * Flag indicates that form has been
	 * submitted via POST
	 *
	 * @var bool
	 */
	protected $bSubmitted = false;

	/**
	 * Array of form field errors
	 * keys should be form field names + '_e', values
	 * are array of error messages. This way a single form field
	 * can have more than one validation error message
	 *
	 * Before form template is parsed, this array is checked
	 * and if not empty it is merged with $aVars array, then
	 * merged array is used in parse() of template
	 *
	 * @var array
	 */
	protected $aErrors = array();

	public function __construct(Registry $oRegistry, $useToken = true){
		$this->oRegistry = $oRegistry;
		$this->useToken = $useToken;
		$tpl = $this->template;
		d('tpl: '.$tpl);
		
		
		$this->aVars = $tpl::getVars();
		d('$this->aVars: '.print_r($this->aVars, 1));
		if('POST' === Request::getRequestMethod()){
			$this->bSubmitted = true;
			$this->validateToken();
		} else {
			$this->addToken();
		}

		$this->init();
	}

	/**
	 * Check to see if form has been submitted
	 *
	 * @return bool true if form submitted, false
	 * if not submitted
	 */
	public function isSubmitted(){

		return $this->bSubmitted;
	}


	public function enableToken(){
		$this->useToken = true;

		return $this;
	}

	public function disableToken(){
		$this->useToken = false;

		d('$this->useToken: '.$this->useToken);

		return $this;
	}


	public function addValidator($field, $func){
		if(!is_callable($func)){
			throw new \InvalidArgumentException('second param passed to addValidator must be a callable funcion. Was: '.var_export($func, true));
		}
		$aFields = $this->getFields();
		if(!in_array($field, $aFields)){
			throw new \Lampcms\DevException('Field '.$field.' does not exist in form. Cannot set validator for non-existent field aFields: '.print_r($aFields, 1));
		}

		$this->aValidators[$field] = $func;
	}

	/**
	 * Run custom validators
	 * Validators can be added via addValidator() method
	 * OR a sub class can implement a doValidate() method
	 * which may contain all necessary validation methods and
	 * must set errors via setError()
	 *
	 * @throws LampcmsDevException
	 *
	 * @return bool true if there are no validation errors,
	 * false otherwise
	 */
	public function validate(){

		if(!empty($this->aValidators)){

			foreach($this->aValidators as $field => $func){
				if(!is_callable($func)){
					throw new \Lampcms\DevException('not callable');
				}

				$val = $this->getSubmittedValue($field);
				if(true !== $res = $func($val)){
					$this->setError($field, $res);
				}
			}
		}

		$this->doValidate();

		return $this->isValid();
	}

	/**
	 * Method that invokes form
	 * validation
	 *
	 * Concrete form class can implement its own
	 * to do custom validation
	 */
	protected function doValidate(){}

	/**
	 * Get values of submitted form fields
	 * Returned values are sanitized by filter_var
	 * and other custom sanitization we have in Request object
	 *
	 * @return array keys are form fields, values are submitted
	 * values
	 */
	public function getSubmittedValues(){
		$aFields = $this->getFields();
		$a = $this->oRegistry->Request->getArray();
		d('$aFields: '.print_r($aFields, 1).' $a: '.print_r($a, 1));

		/**
		 * Order of array_intersect_key is very important!
		 */
		$ret = array_intersect_key($a, array_flip($aFields));
		d('submitted values: '.print_r($ret, 1));

		return $ret;
	}


	/**
	 *
	 * Get value of certain form field
	 * @param string $field
	 * @throws \Lampcms\DevException if $field does not exist in form
	 *
	 * @return string value of submitted field
	 */
	public function getSubmittedValue($field){
		if(!$this->fieldExists($field)){
			throw new \Lampcms\DevException('field '.$field.' does not exist');
		}

		return $this->oRegistry->Request->getParam($field);
	}


	/**
	 *
	 * Check if certain form field exists in form object
	 * @param string $field
	 * @return bool
	 */
	protected function fieldExists($field){
		$aFields = $this->getFields();

		return in_array($field, $aFields);
	}


	/**
	 *
	 * Enter description here ...
	 */
	public function getFields(){
		$aFields = (!empty($this->aFields)) ? array_keys($this->aFields) : array_keys($this->aVars);

		return $aFields;
	}


	/**
	 * Sub-class may implement init() method
	 * in order to initialize form vars.
	 * For example to translate some of the vars into
	 * current language
	 *
	 * @return object $this
	 */
	protected function init(){

		return $this;
	}


	/**
	 * Sets error message
	 * for the form field
	 *
	 * We don't check to see if field name exists
	 * and we don't check if field_e key exists
	 * in template vars. If it does not then
	 * setting of error will not fail but will have
	 * absolutely no meaning since errors will not be shown
	 * on form.
	 *
	 * @todo in the future will automatically
	 * translate the $message into current language
	 * using oI18n
	 *
	 *
	 * @param string $field
	 * @param string $message
	 */
	public function setError($field, $message){
		if(Request::isAjax()){
			\Lampcms\Responder::sendJSON(array('formElementError' => array($field => $messasge)));
		} else {
			$this->aErrors[$field.'_e'][] = $message;
		}

		return $this;
	}

	
	/**
	 * Set error message for the form as a whole.
	 * This error message is not specific to any form field,
	 * it usually appears on top of form as a general error message
	 *
	 * For example: You must wait 5 minutes between posting
	 * This is not due to any element error, just a general error
	 * message.
	 *
	 * The form template MUST have 'formError' variable in it!
	 *
	 * @param string $errMessage
	 */
	public function setFormError($errMessage){

		if(Request::isAjax()){
			\Lampcms\Responder::sendJSON(array('formError' => $errMessage));
		} else {
			$this->aErrors['formError'][] = $errMessage;
		}

		return $this;
	}

	
	/**
	 * 
	 * Set variable (any variable that 
	 * is present in form's template
	 * 
	 * @param string $name
	 * @param string $value
	 * @throws \InvalidArgumentException
	 * 
	 * @return object $this
	 */
	public function setVar($name, $value){
		if(!array_key_exists($name, $this->aVars)){

			throw new \InvalidArgumentException('Var '.$name.' does not exist in this form\'s template aVars: '.print_r($this->aVars, 1));
		}

		$this->aVars[$name] = $value;

		return $this;
	}

	
	/**
	 * 
	 * Magic setter
	 * @param string $name
	 * @param string $val
	 */
	public function __set($name, $val){
		$this->setVar($name, $val);
	}

	
	/**
	 * Getter for $this->aErrors
	 * @return array
	 */
	public function getErrors(){

		return $this->aErrors;
	}

	
	/**
	 * Parse form template using vars/values we set
	 * also if aErrors not empty, merge it with aVars
	 *
	 * @return string html parsed form template
	 */
	public function getForm(){
		d('cp');

		$this->prepareVars()->addErrors();
		$tpl = $this->template;

		/**
		 * Observer can
		 * do setVar() on a passed form object
		 * and add another element to aVars just before
		 * form is rendered
		 *
		 */
		$this->oRegistry->Dispatcher->post($this, 'onBeforeFormRender');

		return $tpl::parse($this->aVars);
	}

	
	/**
	 * @todo here we can do translation of template vars
	 * We will have translateArray() in oTr which will
	 * take array as input, then use keys as strings and values
	 * as fallback values and return array with translated values
	 *
	 * @return object $this
	 */
	protected function prepareVars(){

		if($this->bSubmitted){
			$a = $this->oRegistry->Request->getArray();
			d('a from request: '.print_r($a, 1));
			$this->aVars = array_merge($this->aVars, $a);
		}

		return $this;
	}

	
	/**
	 * It makes sense to call this method ONLY after
	 * you validated the form values yourself and
	 * set errors via setError() method
	 *
	 * @return bool true if no errors has been set,
	 * false otherwise
	 *
	 */
	public function isValid(){

		return 0 === count($this->aErrors);
	}

	
	/**
	 * If aErrors not empty then merge aVars with aErrors
	 *
	 * @return object $this
	 */
	protected function addErrors(){
		if(!empty($this->aErrors)){
			$this->aVars = array_merge($this->aVars, $this->flattenErrors());
			d('$this->aVars: '.print_r($this->aVars, 1));
		}

		return $this;
	}

	
	/**
	 * Turn array of errors into string
	 * in which each element from array becomes
	 * an <li> html element
	 *
	 * @return array where keys are field names + _e
	 * and values are strings contained in <ul> tag
	 *
	 */
	protected function flattenErrors(){
		$ret = array();
		foreach($this->aErrors as $field => $aErrors){
			$ret[$field] = '<ul>';
			foreach($aErrors as $error){
				$ret[$field] .= '<li>'.$error.'</li>';
			}
			$ret[$field] .= '</ul>';
		}
		d('$ret: '.print_r($ret, 1));

		return $ret;
	}

	
	/**
	 * Generate unique ID and store in session
	 * The page will have the meta tag 'version'
	 * with the value of this token
	 * it will be used by ajax based forms when submitting
	 * form via ajax
	 *
	 * The form token is tied to current session but
	 * does not use sessionID and it is also tied to
	 * the concrete form because each concrete form
	 * has different class name
	 *
	 * @IMPORTANT the token is now NOT global, it cannot
	 * be used as value of some meta tag because it will be different
	 * for different forms.
	 *
	 * @return string value of form token
	 * for this class.
	 */
	public static function generateToken()
	{
		if (!array_key_exists('secret', $_SESSION)) {

			$token = uniqid(mt_rand());
			//$_SESSION['secret'] = $token;
			$_SESSION['secret'] = hash('md5', $token);
		}

		return $_SESSION['secret'];
		//return hash('md5', $_SESSION['secret'].get_called_class());
	}


	/**
	 * Add value of 'token' to form's aVars
	 *
	 * @return object $this
	 */
	protected function addToken(){
		if($this->useToken){
			$this->aVars['token'] = static::generateToken();
		}

		return $this;
	}


	/**
	 * Validate submitted 'token' value
	 * agains generateToken()
	 * they must match OR throw TokenException
	 *
	 */
	public function validateToken(){
		d('$this->useToken: '.$this->useToken);
		if(true === $this->useToken){
			if(empty($_SESSION['secret'])){
				throw new \Lampcms\TokenException('Form token not found in session');
			}
				
			$token = $this->oRegistry->Request->token;
			d('submitted form token: '.$token);
			if($token !== $this->generateToken()){
				throw new \Lampcms\TokenException('Invalid security token. You need to reload the page in browser and try submitting this form again');
			}
		}

		return $this;
	}


	/**
	 * Translate some vars like labels and
	 * descriptions of form elements
	 *
	 * @todo actually translate those
	 *
	 */
	protected function translateVars(){
		d('cp');

		return $this;
	}

}