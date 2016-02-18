<?php

/**
 * Order object (single orders only)
 *
 * TODO: order bundle support?
 *
 */
class FmOrder
{

    private $post;

    const FYNDIQ_ID_META_FIELD = 'fyndiq_id';

    const FYNDIQ_HANDLED_ORDER_META_FIELD = '_fyndiq_handled_order';


    public function __construct($postID)
    {
        $this->post = get_post($postID);
    }


    public function getIsHandled()
    {
        //If we're saving the post, look in the HTTP POST data.
        if ($_POST['action'] == 'editpost' and $_POST['post_type'] == 'shop_order') {
            //Is only set if box is ticked.
            return isset($_POST['_fyndiq_handled_order']);
            //Otherwise, look in the metadata.
        } elseif (!get_post_meta($this->getPostID(), FmOrder::FYNDIQ_HANDLED_ORDER_META_FIELD, true)) {
            return 0;
        }
        return 1;
    }

    public function setIsHandled($value)
    {
        /**
         * This might seem inadequate in terms of input sanity,
         * but actually would be no different than an if statement.
         */
        update_post_meta($this->getPostID(), FmOrder::FYNDIQ_HANDLED_ORDER_META_FIELD, (bool)$value);

        $markPair = new stdClass();
        $markPair->id = $this->getFyndiqOrderID();
        $markPair->marked = (bool)$value;

        $data = new stdClass();
        $data->orders = array($markPair);
        try {
            FmHelpers::callApi('POST', 'orders/marked/', $data);
        } catch (Exception $e) {
            FmError::handleError($e->getMessage());
        }
    }


    public function getPostID()
    {
        return $this->post->ID;
    }

    public function getFyndiqOrderID()
    {
        return get_post_meta($this->getPostID(), FmOrder::FYNDIQ_ID_META_FIELD, true);
    }

    public function setFyndiqOrderID($fyndiqID)
    {
        return update_post_meta($this->getPostID(), FmOrder::FYNDIQ_ID_META_FIELD, $fyndiqID);
    }
}
