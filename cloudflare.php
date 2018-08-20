<?php

function merge_to_key_value($array,$glue = ' '){
    $out = Array();
    foreach($array as $k => $v)
        $out[] = $k.$glue.$v;
    return $out;
}

function multiRequest( $data, $options = array() , $headers = Array() ) { // http://www.phpied.com/simultaneuos-http-requests-in-php-with-curl/
    $curly = array();
    $result = array();


    $headers = merge_to_key_value($headers,': ');

    $mh = curl_multi_init();

    foreach ( $data as $id => $d ) {
        $curly[$id] = curl_init();

        $url = ( is_array( $d ) && ! empty( $d['url'] ) ) ? $d['url'] : $d;
        curl_setopt( $curly[$id], CURLOPT_URL,            $url);
        curl_setopt( $curly[$id], CURLOPT_HEADER,         0 );
        curl_setopt( $curly[$id], CURLOPT_HTTPHEADER,     $headers);
        curl_setopt( $curly[$id], CURLOPT_RETURNTRANSFER, 1 );

        if ( is_array( $d ) ) {
            if ( ! empty( $d['post'] ) ) {
                curl_setopt( $curly[ $id ], CURLOPT_POST,       1 );
                curl_setopt( $curly[ $id ], CURLOPT_POSTFIELDS, $d['post'] );
            }
        }
        if ( ! empty( $options ) ) {
            curl_setopt_array( $curly[ $id ], $options );
        }
        curl_multi_add_handle( $mh, $curly[ $id ] );
    }
    $running = null;
    do {
        curl_multi_exec( $mh, $running );
    } while( $running > 0 );

    foreach( $curly as $id => $c ) {
        $result[ $id ] = curl_multi_getcontent( $c );
        curl_multi_remove_handle( $mh, $c );
    }
    curl_multi_close( $mh );

    return $result;
}

$email = '';
$ips = '';
$action  = '';
$apikey = '';
$fail = '';
$zone = '';
$notes = '';

if ( isset( $_POST['run'] ) && '' != trim( $_POST['apikey'] ) && '' != trim( $_POST['email'] ) && '' != trim( $_POST['ips'] ) && '' != trim( $_POST['act'] ) && '' != trim( $_POST['notes'] ) && '' != trim( $_POST['zone'] ) ) {
    $apiURL = 'https://api.cloudflare.com/client/v4/zones';
    $action = $_POST['act'];
    $apiKey = $_POST['apikey'];
    $zone = $_POST['zone'];
    $notes = $_POST['notes'];
    $email = $_POST['email'];
    $ips = $_POST['ips'];
        $ips = preg_replace( '/\s+/', "\n", $ips );
        $ips = array_filter( explode( "\n", $ips ) );

    $data = array();
    foreach ( $ips as $k => $v ) {
        $data[$k]['url'] = $apiURL .'/'. $zone . '/firewall/access_rules/rules';
        $data[$k]['post'] = array();
        $data[$k]['post'] = json_encode(Array(
                'mode' => $action,
                'notes' => $notes,
                'configuration' => Array('target' => 'ip','value' => trim( $v )))
        );
    }
    $requests = multiRequest($data, Array(), Array('X-Auth-Email' => $email,'X-Auth-Key' => $apiKey,'Content-Type' => 'application/json'));

    $output = '';
    foreach ( $requests as $key => $value ) {
        $value = json_decode( $value, true );
        if (!empty($value['result']['id'])) {
            $output .= '<pre>IP: ' . $value['result']['mode'] . ', Action: "' . $value['result']['configuration']['value'] . '", Result: success</pre>';
        } else {
            //error_log( print_r( $value ) );
            $output .= '<pre class="text-danger">' . $value['errors'][0]['code'] . ' (' . $value['errors'][0]['message'] . ')</pre>';
        }
    }

} elseif( isset( $_POST['run'] ) ) {
    $fail = true;
}
?>
<html>
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/bootstrapSuperhero.min.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Bulk Update Cloudflare Firewall rules</h1>
        <?php if ( ! isset( $_POST['run'] ) || $fail ) : ?>
            <p class="lead">This is a simple script to bulk update the firewall rules on Cloudflare.</p>
            <p>It's not rocket science - fill in the form (1 IPv4 address per line) and hit Submit, just make sure you choose the right action...</p>
        <?php if ( $fail ) : ?>
            <div class="alert alert-danger">
                <strong>I give you one simple thing to do</strong>... try again, and pay attention this time!
            </div>
        <?php endif; ?>
            <div class="well">
                <form action="" method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-lg-2 control-label" for="apikey">API Key</label>
                        <div class="col-lg-10">
                            <input type="text" class="form-control" name="apikey" id="apikey" value="<?php echo urlencode( $apikey ); ?>" placeholder="Enter API Key">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-lg-2 control-label" for="zone">Zone Key</label>
                        <div class="col-lg-10">
                            <input type="text" class="form-control" name="zone" id="zone" value="<?php echo urlencode( $zone ); ?>" placeholder="Enter zone ID">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-lg-2 control-label" for="email">Email Address (associated with the API Key)</label>
                        <div class="col-lg-10">
                            <input type="email" class="form-control" name="email" id="email" value="<?php echo urlencode( $email ); ?>" placeholder="Enter email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-lg-2 control-label" for="ips">IPs</label>
                        <div class="col-lg-10">
                            <span class="help-block"><strong>Remember, one per line - IPv4 style only for now</strong></span>
                            <textarea class="form-control" id="ips" name ="ips" rows="10" placeholder="List of IPv4 addresses, 1 per line"><?php echo $ips; ?></textarea>
                        </div>
                    </div>
										<div class="form-group">
                        <label class="col-lg-2 control-label" for="email">notes</label>
                        <div class="col-lg-10">
                            <input type="notes" class="form-control" name="notes" id="notes" value="<?php echo urlencode( $notes ); ?>" placeholder="Enter notes">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-lg-2 control-label" for="act">Action</label>
                        <div class="col-lg-10">
                            <select name="act" id="act" class="form-control">
                                <option>Select an action</option>
                                <option <?php if ( $action === 'block' ) echo 'selected=selected';?> value="block">block</option>
                                <option <?php if ( $action === 'whitelist' ) echo 'selected=selected';?> value="whitelist">whitelist</option>
                                <option <?php if ( $action === 'js_challenge' ) echo 'selected=selected';?> value="js_challenge">js_challenge</option>
                                <option <?php if ( $action === 'challenge' ) echo 'selected=selected';?> value="challenge">challenge</option>
                            </select>
                        </div>
                    </div>
                    <input class="hidden" name="run">
                    <div class="form-group">
                        <div class="col-lg-10 col-lg-offset-2">
                            <button type="reset" class="btn btn-default">Cancel</button>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </div>
                </form>
            </div>

        <?php else : ?>

            <p class="lead">Requests</p>
            <?php echo $output; ?>
            <a href="" class="btn btn-primary">I've got more...</a>

        <?php endif; ?>
    </div>
</body>
</html>
