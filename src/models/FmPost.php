<?php
/**
 * Class FmPost
 *
 * Parent class that handles Wordpress post related stuff that both orders and products use
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmPost
{
    protected $post;

    public function __construct($postId)
    {
        $this->post = get_post($postId);

        if (is_null($this->post)) {
            throw new Exception(
                __(
                    'Whoops. An error occurred when generating a post.' .
                    'Please tell Fyndiq: FmPost instantiated with bad ID',
                    'fyndiq'
                )
            );
        }
    }

    public function getPostId()
    {
        return $this->post->ID;
    }

    public function getPost()
    {
        return $this->post;
    }

    protected function setMetaData($key, $value, $method = 'update')
    {
        switch ($method) {
            case 'update':
                return update_post_meta($this->getPostId(), $key, $value);
                break;

            case 'add':
                return add_post_meta($this->getPostId(), $key, $value, true);
                break;

            default:
                return null;
        }
    }

    protected function getMetaData($key)
    {
        return get_post_meta($this->getPostId(), $key, true);
    }

    public static function getWordpressCurrentPostID()
    {
        return get_the_ID();
    }

    public static function getRequestPostsArray()
    {
        if (isset($_REQUEST['post'])) {
            return $_REQUEST['post'];
        }
        return array();
    }
}
