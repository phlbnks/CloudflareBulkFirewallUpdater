<?php

function multiRequest( $data, $options = array() ) { // http://www.phpied.com/simultaneuos-http-requests-in-php-with-curl/
    $curly = array();
    $result = array();
    $mh = curl_multi_init();

    foreach ( $data as $id => $d ) {
        $curly[$id] = curl_init();

        $url = ( is_array( $d ) && ! empty( $d['url'] ) ) ? $d['url'] : $d;
        curl_setopt( $curly[$id], CURLOPT_URL,            $url );
        curl_setopt( $curly[$id], CURLOPT_HEADER,         0 );
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

if ( isset( $_POST['run'] ) && '' != trim( $_POST['apikey'] ) && '' != trim( $_POST['email'] ) && '' != trim( $_POST['ips'] ) && '' != trim( $_POST['act'] ) ) {
    $apiURL = 'https://www.cloudflare.com/api_json.html';
    $action = $_POST['act'];
    $apiKey = $_POST['apikey'];
    $email = $_POST['email'];
    $ips = $_POST['ips'];
        $ips = preg_replace( '/\s+/', "\n", $ips );
        $ips = array_filter( explode( "\n", $ips ) );

    $data = array();
    foreach ( $ips as $k => $v ) {
        $data[$k]['url'] = $apiURL;
        $data[$k]['post'] = array();
        $data[$k]['post']['a'] = $action;
        $data[$k]['post']['tkn'] = $apiKey;
        $data[$k]['post']['email'] = $email;
        $data[$k]['post']['key'] = trim( $v );
    }
    $requests = multiRequest($data);

    $output = '';
    foreach ( $requests as $key => $value ) {
        $value = json_decode( $value, true );
        if ( 'success' === $value['result'] ) {
            $output .= '<pre>IP: ' . $value['response']['result']['ip'] . ', Action: "' . $value['response']['result']['action'] . '", Result: success</pre>';
        } else {
            error_log( print_r( $value ) );
            $output .= '<pre class="text-danger">' . $value['msg'] . ' (' . $value['err_code'] . ')</pre>';
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
                        <label class="col-lg-2 control-label" for="act">Action</label>
                        <div class="col-lg-10">
                            <select name="act" id="act" class="form-control">
                                <option>Select an action</option>
                                <option <?php if ( $action === 'wl' ) echo 'selected=selected';?> value="wl">WhiteList</option>
                                <option <?php if ( $action === 'ban' ) echo 'selected=selected';?> value="ban">BlackList</option>
                                <option <?php if ( $action === 'nul' ) echo 'selected=selected';?> value="nul">Remove</option>
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
