<?php

/**
 * Allow-list sanitiser for rich text coming from the Quill editor (notes, descriptions).
 *
 * The stored HTML is later injected with v-html / innerHTML, so anything that can run
 * script must be removed before it is saved: unknown elements are unwrapped (their text
 * is kept), dangerous elements are dropped with their content, and only known-safe
 * attributes, URL schemes and CSS properties survive.
 */
class HtmlSanitizer {

	/** Elements kept as they are. */
	private static $allowedTags = array(
		'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'mark',
		'sub', 'sup', 'small', 'a', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img',
		'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'colgroup', 'col', 'caption', 'font',
	);

	/** Elements removed together with everything inside them. */
	private static $droppedTags = array(
		'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta',
		'base', 'form', 'input', 'button', 'select', 'option', 'textarea', 'svg', 'math', 'template',
		'noscript', 'title', 'head', 'audio', 'video', 'source', 'track', 'canvas', 'dialog',
	);

	private static $globalAttributes = array('class', 'style', 'title', 'dir', 'lang');

	private static $tagAttributes = array(
		'a' => array('href', 'target', 'rel'),
		'img' => array('src', 'alt', 'width', 'height'),
		'td' => array('colspan', 'rowspan'),
		'th' => array('colspan', 'rowspan'),
		'col' => array('span', 'width'),
		'ol' => array('start', 'type'),
		'font' => array('color', 'size', 'face'),
	);

	private static $allowedCss = array(
		'color', 'background-color', 'background', 'font-size', 'font-weight', 'font-style', 'font-family',
		'text-align', 'text-decoration', 'text-indent', 'line-height', 'letter-spacing', 'white-space',
		'vertical-align', 'width', 'height', 'max-width', 'min-width', 'margin', 'margin-left',
		'margin-right', 'margin-top', 'margin-bottom', 'padding', 'padding-left', 'padding-right',
		'padding-top', 'padding-bottom', 'border', 'border-collapse', 'list-style-type', 'direction',
	);

	public static function clean($html) {
		$html = (string)$html;
		// No markup at all: nothing can execute, keep the text byte for byte.
		if ($html === '' || strpos($html, '<') === false) {
			return $html;
		}
		if (!class_exists('DOMDocument')) {
			// Without the DOM extension fall back to text only rather than storing raw HTML.
			return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
		}

		$previous = libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->loadHTML(
			'<?xml encoding="UTF-8"><html><body><div id="__sanitizer_root__">' . $html . '</div></body></html>',
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$root = $doc->getElementById('__sanitizer_root__');
		if (!$root) {
			return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
		}

		self::cleanChildren($root);

		$out = '';
		foreach ($root->childNodes as $child) {
			$out .= $doc->saveHTML($child);
		}
		return $out;
	}

	private static function cleanChildren(DOMNode $parent) {
		// Snapshot first: the live list changes while nodes are removed or unwrapped.
		$children = array();
		foreach ($parent->childNodes as $child) {
			$children[] = $child;
		}
		foreach ($children as $node) {
			if ($node instanceof DOMText) {
				// DOMCdataSection extends DOMText; saveHTML would emit it unescaped.
				if ($node instanceof DOMCdataSection) {
					$parent->replaceChild($parent->ownerDocument->createTextNode($node->data), $node);
				}
				continue;
			}
			if (!($node instanceof DOMElement)) {
				// comments, processing instructions, doctype...
				$parent->removeChild($node);
				continue;
			}
			$tag = strtolower($node->nodeName);
			if (in_array($tag, self::$droppedTags, true)) {
				$parent->removeChild($node);
				continue;
			}
			self::cleanChildren($node);
			if (!in_array($tag, self::$allowedTags, true)) {
				// Unknown element: keep its (already cleaned) content, drop the element itself.
				while ($node->firstChild) {
					$parent->insertBefore($node->firstChild, $node);
				}
				$parent->removeChild($node);
				continue;
			}
			self::cleanAttributes($node, $tag);
		}
	}

	private static function cleanAttributes(DOMElement $node, $tag) {
		$allowed = self::$globalAttributes;
		if (isset(self::$tagAttributes[$tag])) {
			$allowed = array_merge($allowed, self::$tagAttributes[$tag]);
		}
		$names = array();
		foreach ($node->attributes as $attribute) {
			$names[] = $attribute->nodeName;
		}
		foreach ($names as $name) {
			$lower = strtolower($name);
			$value = $node->getAttribute($name);
			$isData = strpos($lower, 'data-') === 0 && preg_match('/^data-[a-z0-9_-]+$/', $lower);
			if (!$isData && !in_array($lower, $allowed, true)) {
				$node->removeAttribute($name);
				continue;
			}
			if ($lower === 'style') {
				$css = self::cleanCss($value);
				if ($css === '') {
					$node->removeAttribute($name);
				} else {
					$node->setAttribute($name, $css);
				}
			} else if ($lower === 'href') {
				if (!self::isSafeUrl($value, false)) {
					$node->removeAttribute($name);
				}
			} else if ($lower === 'src') {
				if (!self::isSafeUrl($value, true)) {
					$node->removeAttribute($name);
				}
			}
		}
		if ($tag === 'a' && strtolower($node->getAttribute('target')) === '_blank') {
			$node->setAttribute('rel', 'noopener noreferrer');
		}
	}

	/** http(s), mailto, tel and relative URLs; for images also inline raster data. */
	private static function isSafeUrl($url, $isImage) {
		// Browsers ignore whitespace and control characters inside the scheme.
		$compact = preg_replace('/[\x00-\x20\x7f]+/', '', (string)$url);
		if ($compact === '') {
			return false;
		}
		if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $m)) {
			return true; // relative URL, fragment or protocol-relative
		}
		$scheme = strtolower($m[1]);
		if (in_array($scheme, array('http', 'https'), true)) {
			return true;
		}
		if (!$isImage) {
			return in_array($scheme, array('mailto', 'tel'), true);
		}
		return (bool)preg_match('#^data:image/(png|jpe?g|gif|webp|bmp);base64,[a-z0-9+/=]+$#i', $compact);
	}

	private static function cleanCss($style) {
		$style = (string)$style;
		// Comments and escapes are how blocked tokens get smuggled past simple checks.
		if (strpos($style, '\\') !== false || strpos($style, '/*') !== false) {
			return '';
		}
		$kept = array();
		foreach (explode(';', $style) as $declaration) {
			$parts = explode(':', $declaration, 2);
			if (count($parts) !== 2) {
				continue;
			}
			$property = strtolower(trim($parts[0]));
			$value = trim($parts[1]);
			if ($value === '' || !in_array($property, self::$allowedCss, true)) {
				continue;
			}
			if (preg_match('/url\s*\(|expression|javascript|@import|behavior|[<>{}]/i', $value)) {
				continue;
			}
			$kept[] = $property . ': ' . $value;
		}
		return implode('; ', $kept);
	}
}
