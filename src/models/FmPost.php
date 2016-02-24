<?php
/**
 * Class FmPost
 *
 * Parent class that handles Wordpress post related stuff that both orders and products use
 */

class FmPost
{
    protected $post;

    public function __construct($postId)
    {
        $this->post = get_post($postId);
    }

    public function getPostId()
    {
        return $this->post->ID;
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
}
