<?php


/**
 * markdownext filter. using Michelf markdown extra
 * @package    filters
 */

use \Michelf\MarkdownExtra;

/**
 * markdownext filter.
 *
 * @package    filters
 */
class cosmarkdownext {

    /**
     *
     * @param array     array of elements to filter.
     * @return <type>
     */
    public static function filter($text){


        static $md;
        if (!$md){
            $md = new MarkdownExtra();
        }

        $md->no_entities = false;
        $md->no_markup = false;

        $text = $md->transform($text);
	return $text; //$parser->transform($text);
    }
}

class filters_cosmarkdownext extends cosmarkdownext {}
