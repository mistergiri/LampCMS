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


namespace Lampcms;



/**
 * Class for pagination of array
 * or mongo Cursor
 *
 * @todo this is a replacement for
 * the paginateArray from clsWebPage, remove
 * the one from clsWebpage soon.
 *
 * @todo make this extend the new LampcmsObject, the
 * one that has factory using 'static' instead of 'self'
 *
 * @author Dmitri Snytkine
 *
 */
class Paginator extends LampcmsObject
{
	protected $oRegistry;

	protected $oPager;

	public function __construct(Registry $oRegistry){
		$this->oRegistry = $oRegistry;
	}


	/**
	 * Getter for oPager object
	 *
	 * @return object Pager (from pear)
	 */
	public function getPager(){
		return $this->oPager;
	}

	/**
	 * Main entry method to paginate data
	 * if mongo cursor is passed, it will
	 * also set offset and limit on the cursor itself
	 *
	 * @param mixed int|array|object MongoCursor $arrData
	 * @param int $perPage
	 * @param array $arrExtraParams
	 *
	 * @throws LampcmsDevException
	 */
	public function paginate($arrData = null, $perPage, $arrExtraParams = array())
	{

		$mongoCursor = null;

		if (2 > 1) // was: FALSE == MOBILE_BROWSER_SETTINGS
		{
			$arrParams = array('mode'=>'Sliding', 'fileName'=>'page%d.html', 'path'=>'', 'append'=>false, 'perPage'=>$perPage, 'delta'=>2, 'urlVar'=>'pageID');

			if (!empty($arrData)) {
				if (is_array($arrData)) {
					d('arrData: '.print_r($arrData, true));
					$arrParams['itemData'] = $arrData;
				} elseif (is_numeric($arrData)) {
					d('totalItems: '.$arrData);
					$arrParams['totalItems'] = $arrData;
				} elseif (is_object($arrData) && ($arrData instanceof \MongoCursor)){
					d('got mongo cursor');
					$mongoCursor = $arrData;
					$arrParams['totalItems'] = $mongoCursor->count(true);
					d('totalItems: '.$arrParams['totalItems']);
				}

				else {
					throw new DevException('wrong type for $arrData param. It must be array or numeric value. Passed: '.gettype($arrData).' '.get_class($addData));
				}
			}

			if (!empty($arrExtraParams)) {
				$arrParams = array_merge($arrParams, $arrExtraParams); // this way we can pass extra parameters here
			}

			include_once(LAMPCMS_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Pear'.DIRECTORY_SEPARATOR.'Pager.php');
			include_once(LAMPCMS_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'Pear'.DIRECTORY_SEPARATOR.'Pager'.DIRECTORY_SEPARATOR.'Common.php');
		
			$this->oPager = \Pager::factory($arrParams);
			//var_dump($this->oPager);
			//exit;

			if(null !== $mongoCursor){
				$curPage = $this->oPager->getCurrentPageID();
				d('curPage: '.$curPage);
				$skip = ($this->oPager->getCurrentPageID() - 1) * $perPage;
				d('skip: '.$skip);

				/**
				 * We skip if total > self::PER_PAGE
				 * meaning if we need pagination
				 * No need to skip if total is not greater than per page
				 * or if we are at first page (skip is 0 then)
				 *
				 */
				if( ($skip > 0) && ($arrParams['totalItems'] > $perPage) ){
					$mongoCursor->skip($skip);
				}

				$mongoCursor->limit($perPage);
			}

			$arrPagerData = $this->oPager->getPageData();
			

			d('$arrPagerData: '.print_r($arrPagerData, true));



		} else {
			/**
			 * @todo finish this
			 * its is not complete yet and will not work properly
			 */
			// setting for mobile browser pager
			$arrParams = array('mode'=>'Sliding', 'append'=>false, 'perPage'=>'10', 'delta'=>0, 'urlVar'=>'pageID', 'path'=>$strPathForPaging, 'prevImg'=>'&lt; Prev', 'nextImg'=>'Next &gt;', 'fileName'=>'%d.htm', 'curPageSpanPre'=>'Pg. ', 'curPageSpanPost'=>'', 'spacesAfterSeparator'=>'0', 'spacesBeforeSeparator'=>'0', 'itemData'=>$arrRecentPosts);

			$this->oPager = \Pager::factory($arrParams);
			$arrData = $this->oPager->getPageData();
			$arrLinks = $this->oPager->getLinks();

			$arrPagerData = $arrData;

		}

		return $arrPagerData;

	} // end paginateArray


	/**
	 * Get HTML block with pagination links
	 *
	 * @todo translate word "Pages"
	 *
	 * @return string html with pagination links
	 */
	public function getLinks(){
		$links = $this->oPager->getLinks();

		return (is_array($links) && !empty($links['all'])) ? '<div class="qpages"><span class="pager_title">Pages</span> : '.$links['all'].'</div>' : '';
	}
}