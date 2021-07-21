<?php
use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array();
    $partials = array();
    $cx = array(
        'flags' => array(
            'jstrue' => false,
            'jsobj' => false,
            'jslen' => false,
            'spvar' => true,
            'prop' => false,
            'method' => false,
            'lambda' => false,
            'mustlok' => false,
            'mustlam' => false,
            'mustsec' => false,
            'echo' => false,
            'partnc' => false,
            'knohlp' => false,
            'debug' => isset($options['debug']) ? $options['debug'] : 1,
        ),
        'constants' => array(),
        'helpers' => isset($options['helpers']) ? array_merge($helpers, $options['helpers']) : $helpers,
        'partials' => isset($options['partials']) ? array_merge($partials, $options['partials']) : $partials,
        'scopes' => array(),
        'sp_vars' => isset($options['data']) ? array_merge(array('root' => $in), $options['data']) : array('root' => $in),
        'blparam' => array(),
        'partialid' => 0,
        'runtime' => '\LightnCandy\Runtime',
    );
    
    $inary=is_array($in);
    return '<div class="biddingbox">
  <ul>
    '.(($inary && isset($in['realized'])) ? $in['realized'] : null).'
    '.(($inary && isset($in['link_text'])) ? $in['link_text'] : null).'
    '.(($inary && isset($in['igavel'])) ? $in['igavel'] : null).'
    '.(($inary && isset($in['bidsquare'])) ? $in['bidsquare'] : null).'
    '.(($inary && isset($in['low_est'])) ? $in['low_est'] : null).'
    '.(($inary && isset($in['high_est'])) ? $in['high_est'] : null).'
    '.(($inary && isset($in['share_this'])) ? $in['share_this'] : null).'
  </ul>
</div>';
};
?>