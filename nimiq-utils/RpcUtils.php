<?php
declare(strict_types = 1);

namespace Nimiq\Utils;

include_once( 'JSONUtils.php' );

use Nimiq\Utils\JSONUtils;

class RpcUtils
{
    public static function prepareRedirectInvocation(string $targetURL, int $id, string $returnURL, string $command, array $args, string $responseMethod): string
    {
        // Cut a potential fragment off the target URL
        $targetUrl = explode('#', $targetURL)[0];

        $fragment = [
            'id' => $id,
            'returnURL' => $returnURL,
            'command' => $command,
            'responseMethod' => $responseMethod,
        ];

        if (is_array($args)) {
            $fragment['args'] = JSONUtils::stringify($args);
        }

        // Test if we need to add a trailing slash
        $targetUrlComponents = parse_url($targetUrl);
        if(empty($targetUrlComponents['path']) && empty($targetUrlComponents['query']) && substr($targetUrl, -1) !== '/') {
            $targetUrl .= '/';
        }

        // Append fragment
        $targetUrl .= '#' . http_build_query($fragment);

        return $targetUrl;
    }
}
