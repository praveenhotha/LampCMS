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


namespace Lampcms;
use Lampcms\FS\Path;

class AvatarParser extends LampcmsObject
{

	public function __construct(Registry $o){
		$this->oRegistry = $o;
	}


	/**
	 * Parse the avatar file in $tempPath,
	 * by creating small square image from it,
	 * save into file system and then add path to new avatar
	 * in User object as 'avatar' element
	 *
	 *
	 * @param User $oUser
	 * @param unknown_type $tempPath
	 * @throws \Lampcms\Exception
	 *
	 * @return object $this
	 */
	public function addAvatar(User $oUser, $tempPath){

		d('$tempPath: '.$tempPath);
		if(empty($tempPath)){
			d('no avatar to add');
			return $this;
		}

		$size = $this->oRegistry->Ini->AVATAR_SQUARE_SIZE;

		$avatarDir = LAMPCMS_DATA_DIR.'img'.DS.'avatar'.DS.'sqr'.DS;
		d('$avatarDir: '.$avatarDir);

		$savePath = Path::prepare($oUser->getUid(), $avatarDir);
		d('$savePath: '.$savePath);
		
		/**
		 * Create avatar and save it
		 * with compression level of 80% (small compression)
		 */
		try{
			$ImgParser = \Lampcms\Image\Editor::factory($this->oRegistry)
			->loadImage($tempPath)
			->makeSquare($size);
			$savePath .= $ImgParser->getExtension();
			$ImgParser->save($avatarDir.$savePath, null, 80);
			d('avatar saved to '.$savePath);
		} catch(\Lampcms\ImageException $e){
			e('ImageException caught in: '.$e->getFile().' on line: '.$e->getLine().' error: '.$e->getMessage());
			throw new \Lampcms\Exception('Unable to process your avatar image at this time');
		}

		/**
		 * Now remove tempPath file
		 */
		@\unlink($tempPath);

		/**
		 * Now add the path to avatar
		 * to user object
		 * save() is not invoked on User object here!
		 * Either rely on auto-save (may not work in case User is
		 * actually the Viewer object) or call save()
		 * from a function that invoked this method
		 */
		$oUser['avatar'] = $savePath;

		return $this;
	}


	/**
	 * Given an path to avatar remove it from User object
	 * if it looks like local avatar also remove it from
	 * the file system
	 *
	 * @todo unfinished
	 * 
	 * @param object $oUser object of type User
	 * @param string $src path to avatar
	 *
	 * @return object $this
	 */
	public function removeAvatar(User $oUser, $src){

		if(0 === \strncmp($src, 'http', 4)){
			/**
			 * External avatar, just remove from User object and done
			 */
			$external = $oUser['avatar_external'];
			if($src == $external){
				$oUser['avatar_external'] = null;
			} 
			
			/**
			 * What to do if user wants to remove gravatar image?
			 * We can mark nogravatar = true in user object
			 * and then it will not use gravatar ever again
			 * there will not be any way to undo it...
			 */
			$oUser['noavatar'] = true;
			
			return $this;
		}
		
		/**
		 * If this was not external avatar then must
		 * remove from File System and 
		 * then remove from User object
		 */
		
		
		return $this;
	}
}