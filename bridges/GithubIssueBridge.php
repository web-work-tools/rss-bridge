<?php
class GithubIssueBridge extends BridgeAbstract {

	const MAINTAINER = 'Pierre Mazière';
	const NAME = 'Github Issue';
	const URI = 'https://github.com/';
	const CACHE_TIMEOUT = 600; // 10min
	const DESCRIPTION = 'Returns the issues or comments of an issue of a github project';

	const PARAMETERS = array(
		'global' => array(
			'u' => array(
				'name' => 'User name',
				'required' => true
			),
			'p' => array(
				'name' => 'Project name',
				'required' => true
			)
		),
		'Project Issues' => array(
			'c' => array(
				'name' => 'Show Issues Comments',
				'type' => 'checkbox'
			)
		),
		'Issue comments' => array(
			'i' => array(
				'name' => 'Issue number',
				'type' => 'number',
				'required' => true
			)
		)
	);

	public function getName(){
		$name = $this->getInput('u') . '/' . $this->getInput('p');
		switch($this->queriedContext) {
		case 'Project Issues':
			$prefix = static::NAME . 's for ';
			if($this->getInput('c')) {
				$prefix = static::NAME . 's comments for ';
			}
			$name = $prefix . $name;
			break;
		case 'Issue comments':
			$name = static::NAME . ' ' . $name . ' #' . $this->getInput('i');
			break;
		default: return parent::getName();
		}
		return $name;
	}

	public function getURI(){
		if(null !== $this->getInput('u') && null !== $this->getInput('p')) {
			$uri = static::URI . $this->getInput('u') . '/'
				. $this->getInput('p') . '/issues';
			if($this->queriedContext === 'Issue comments') {
				$uri .= '/' . $this->getInput('i');
			} elseif($this->getInput('c')) {
				$uri .= '?q=is%3Aissue+sort%3Aupdated-desc';
			}
			return $uri;
		}

		return parent::getURI();
	}

	private function buildGitHubIssueCommentUri($issue_number, $comment_id) {
		// https://github.com/<user>/<project>/issues/<issue-number>#<id>
		return static::URI
		. $this->getInput('u')
		. '/'
		. $this->getInput('p')
		. '/issues/'
		. $issue_number
		. '#'
		. $comment_id;
	}

	private function extractIssueEvent($issueNbr, $title, $comment){

		$uri = buildGitHubIssueCommentUri($issueNbr, $comment->getAttribute('id'));

		$author = $comment->find('.author', 0)->plaintext;

		$title .= ' / ' . trim($comment->plaintext);

		$content = $title;
		if (null !== $comment->nextSibling()) {
			$content = $comment->nextSibling()->innertext;
			if ($comment->nextSibling()->nodeName() === 'span') {
				$content = $comment->nextSibling()->nextSibling()->innertext;
			}
		}

		$item = array();
		$item['author'] = $author;
		$item['uri'] = $uri;
		$item['title'] = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
		$item['timestamp'] = strtotime(
			$comment->find('relative-time', 0)->getAttribute('datetime')
		);
		$item['content'] = $content;
		return $item;
	}

	private function extractIssueComment($issueNbr, $title, $comment){

		$uri = buildGitHubIssueCommentUri($issueNbr, $comment->id);

		$author = $comment->find('.author', 0)->plaintext;

		$title .= ' / ' . trim(
			$comment->find('.timeline-comment-header-text', 0)->plaintext
		);

		$content = $comment->find('.comment-body', 0)->innertext;

		$item = array();
		$item['author'] = $author;
		$item['uri'] = $uri;
		$item['title'] = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
		$item['timestamp'] = strtotime(
			$comment->find('relative-time', 0)->getAttribute('datetime')
		);
		$item['content'] = $content;
		return $item;
	}

	private function extractIssueComments($issue){
		$items = array();
		$title = $issue->find('.gh-header-title', 0)->plaintext;
		$issueNbr = trim(
			substr($issue->find('.gh-header-number', 0)->plaintext, 1)
		);

		$comments = $issue->find('
			[id^="issue-"] > .comment,
			[id^="issuecomment-"] > .comment,
			[id^="event-"],
			[id^="ref-"]
			');
		foreach($comments as $comment) {

			if (!$comment->hasChildNodes()) {
				continue;
			}

			if (!$comment->hasClass('discussion-item-header')) {
				$item = $this->extractIssueComment($issueNbr, $title, $comment);
				$items[] = $item;
				continue;
			}

			while ($comment->hasClass('discussion-item-header')) {
				$item = $this->extractIssueEvent($issueNbr, $title, $comment);
				$items[] = $item;
				$comment = $comment->nextSibling();
				if (null == $comment) {
					break;
				}
				$classes = explode(' ', $comment->getAttribute('class'));
			}

		}
		return $items;
	}

	public function collectData(){
		$html = getSimpleHTMLDOM($this->getURI())
			or returnServerError(
				'No results for Github Issue ' . $this->getURI()
			);

		switch($this->queriedContext) {
		case 'Issue comments':
			$this->items = $this->extractIssueComments($html);
			break;
		case 'Project Issues':
			foreach($html->find('.js-active-navigation-container .js-navigation-item') as $issue) {
				$info = $issue->find('.opened-by', 0);
				$issueNbr = substr(
					trim($info->plaintext), 1, strpos(trim($info->plaintext), ' ')
				);

				$item = array();
				$item['content'] = '';

				if($this->getInput('c')) {
					$uri = static::URI . $this->getInput('u')
						. '/' . $this->getInput('p') . '/issues/' . $issueNbr;
					$issue = getSimpleHTMLDOMCached($uri, static::CACHE_TIMEOUT);
					if($issue) {
						$this->items = array_merge(
							$this->items,
							$this->extractIssueComments($issue)
						);
						continue;
					}
					$item['content'] = 'Can not extract comments from ' . $uri;
				}

				$item['author'] = $info->find('a', 0)->plaintext;
				$item['timestamp'] = strtotime(
					$info->find('relative-time', 0)->getAttribute('datetime')
				);
				$item['title'] = html_entity_decode(
					$issue->find('.js-navigation-open', 0)->plaintext,
					ENT_QUOTES,
					'UTF-8'
				);

				$comment_count = 0;
				if($span = $issue->find('a[aria-label*="comment"] span', 0)) {
					$comment_count = $span->plaintext;
				}

				$item['content'] .= "\n" . 'Comments: ' . $comment_count;
				$item['uri'] = self::URI
					. $issue->find('.js-navigation-open', 0)->getAttribute('href');
				$this->items[] = $item;
			}
			break;
		}

		array_walk($this->items, function(&$item){
			$item['content'] = preg_replace('/\s+/', ' ', $item['content']);
			$item['content'] = str_replace(
				'href="/',
				'href="' . static::URI,
				$item['content']
			);
			$item['content'] = str_replace(
				'href="#',
				'href="' . substr($item['uri'], 0, strpos($item['uri'], '#') + 1),
				$item['content']
			);
			$item['title'] = preg_replace('/\s+/', ' ', $item['title']);
		});
	}
}
