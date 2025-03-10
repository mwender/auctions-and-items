<?php
use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
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
    return '<div class="alert alert-'.htmlspecialchars((string)(($inary && isset($in['type'])) ? $in['type'] : null), ENT_QUOTES, 'UTF-8').'" role="alert">
  <div class="alert-message">
    '.((LR::ifvar($cx, (($inary && isset($in['heading'])) ? $in['heading'] : null), false)) ? '<h4 class="alert-heading">'.htmlspecialchars((string)(($inary && isset($in['heading'])) ? $in['heading'] : null), ENT_QUOTES, 'UTF-8').'</h4>' : '').'
    '.(($inary && isset($in['message'])) ? $in['message'] : null).'
  </div>
</div>';
};
?>