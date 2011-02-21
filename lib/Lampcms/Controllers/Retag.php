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
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms\Controllers;


use \Lampcms\WebPage;
use \Lampcms\ReputationAcl;
use \Lampcms\TagsNormalizer;
use \Lampcms\Request;
use \Lampcms\Responder;

class Retag extends WebPage
{
	protected $membersOnly = true;

	protected $requireToken = true;

	protected $aRequired = array('qid', 'tags');

	/**
	 * Question being deleted
	 *
	 * @var object of type Question
	 */
	protected $oQuestion;

	/**
	 * Array of submitted tags
	 * after they are run through TagsNormalizer
	 *
	 * @var array
	 */
	protected $aSubmitted;


	protected function main(){
		$this->getQuestion()
		->checkPermission()
		->checkForChanges()
		->removeOldTags()
		->updateQuestion()
		->addNewTags()
		->postEvent()
		->returnResult();
	}


	/**
	 * Post onRetag event
	 *
	 * @return object $this
	 */
	protected function postEvent(){
		$this->oRegistry->Dispatcher->post($this->oQuestion, 'onRetag', $this->aSubmitted);

		return $this;
	}


	/**
	 * Check to make sure Viewer has permission
	 * to retag.
	 * Permitted to retag are: owner of question,
	 * moderator/admin or user with reputation
	 *
	 * @return object $this
	 *
	 */
	protected function checkPermission(){

		if($this->oRegistry->Viewer->getUid() != $this->oQuestion->getOwnerId()
		&& ($this->oRegistry->Viewer->getReputation() < ReputationAcl::RETAG)){

			$this->checkAccessPermission('retag');
		}

		return $this;
	}


	/**
	 * Update USER_TAGS and QUESTION_TAGS collections
	 * to remove old tags that belong to this questions
	 *
	 * @return object $this
	 */
	protected function removeOldTags(){
		\Lampcms\Qtagscounter::factory($this->oRegistry)->removeTags($this->oQuestion);
		\Lampcms\UserTags::factory($this->oRegistry)->removeTags($this->oQuestion);

		/**
		 * Also update UNANSWERED_TAGS if this question
		 * is unanswered
		 */
		if(0 === $this->oQuestion['i_sel_ans']){
			d('going to remove from Unanswered tags');
			\Lampcms\UnansweredTags::factory($this->oRegistry)->remove($this->oQuestion);
		}

		return $this;
	}


	/**
	 * Update USER_TAGS and QUESTION_TAGS collections
	 * to add new tags that belong to this questions
	 *
	 * @return object $this
	 */
	protected function addNewTags(){
		\Lampcms\Qtagscounter::factory($this->oRegistry)->parse($this->oQuestion);
		\Lampcms\UserTags::factory($this->oRegistry)->addTags($this->oQuestion['i_uid'], $this->oQuestion);

		if(0 === $this->oQuestion['i_sel_ans']){
			d('going to add to Unanswered tags');
			\Lampcms\UnansweredTags::factory($this->oRegistry)->set($this->oQuestion);
		}
		
		return $this;
	}


	/**
	 * Update question object with
	 * new values related to tags
	 *
	 * @return object $this
	 */
	protected function updateQuestion(){
		$this->oQuestion->offsetSet('a_tags', $this->aSubmitted);
		$this->oQuestion->offsetSet('tags_html', \tplQtags::loop($this->aSubmitted, false));
		$this->oQuestion->offsetSet('tags_c', trim(\tplQtagsclass::loop($this->aSubmitted, false)));

		$this->oQuestion->updateLastModified()->save();

		return $this;
	}


	/**
	 * Create $this->oQuestion object
	 *
	 * @throws \Lampcms\Exception if question
	 * not found or is marked as deleted
	 *
	 * @return object $this
	 */
	protected function getQuestion(){

		$coll = $this->oRegistry->Mongo->QUESTIONS;
		$a = $coll->findOne(array('_id' => (int)$this->oRequest['qid']));
		d('a: '.print_r($a, 1));

		if(empty($a) || !empty($a['i_del_ts'])){

			throw new \Lampcms\Exception('Question not found');
		}

		$this->oQuestion = new \Lampcms\Question($this->oRegistry, $a);

		return $this;
	}


	/**
	 * Make sure that new tags are
	 * different from tags that already
	 * in the question
	 *
	 * @throws \Lampcms\Exception in case tags
	 * has not changed
	 *
	 * @return object $this
	 */
	protected function checkForChanges(){
		$aTags = $this->oQuestion['a_tags'];
		$this->aSubmitted = TagsNormalizer::parse(explode(' ', $this->oRequest['tags']));

		d('aTags: '.print_r($aTags, 1));
		d('aSubmitted: '.print_r($this->aSubmitted, 1));

		if($aTags == $this->aSubmitted){
			throw new \Lampcms\Exception('You have not changed any tags');
		}

		return $this;
	}



	protected function returnResult(){
		/**
		 * @todo translate string
		 */
		$message = 'Question retagged successfully';

		if(Request::isAjax()){
			$ret = array('alert' => $message, 'reload' => 1000);

			Responder::sendJSON($ret);
		}

		Responder::redirectToPage($this->oQuestion->getUrl());
	}

}
