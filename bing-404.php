<?php
/*
Plugin Name: Bing 404
Plugin URI: http://wordpress.org/extend/plugins/bing-404
Description: Don't let 404 errors be an exit sign for your blog readers, help them out. This plugin uses Microsoft's Bing API to suggest a list of possible URLs they might want to visit since the one they requested can't be found.
pages on your site that they might be interested in.
Version: 1.0.1
Author: Cal Evans <cal@blueparabola.com>
Author URI: http://wordpress.org/extend/plugins/bing-404
*/

/*  Copyright 2010 Microsoft
 *
 * In the original BSD license, both occurrences of the phrase "COPYRIGHT
 * HOLDERS AND CONTRIBUTORS" in the disclaimer read "REGENTS AND CONTRIBUTORS".
 *
 * Copyright (c) 2010, Microsoft
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *  - Neither the name of the Microsoft nor the names of its contributors
 *    may be used to endorse or promote products derived from this
 *    software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * Version List
 * 1.0.1 2010-06-04
 * Updated the Msft Library to include missing Exceptions
 *
 * 1.0 2010-03-24
 * Initial public release
 *
 * 0.7B 2010-03-15
 * Added the option to use a default 404 page included with the plugin. In many
 * cases, this will work just fine, however, there will be themes that won't
 * work correctly. This keeps the user from having to modify their template if
 * they aren't comfortable with that.
 *
 * 0.6B
 * First version used in production
 */

/**
 * Register the admin pages and actions
 */
if ( is_admin() ) {
    // admin actions
    add_action( 'admin_menu', 'bing404_setup_admin' );
    add_action( 'admin_menu', 'bing404_admin_add_page' );
    add_action( 'wp_ajax_bing404_keyAction', 'bing404_key_check_callback' );
}

/**
 * Register the 404 hook.
 * 
 * Only register the 404 hook if the user wants to use our included template.
 */
if ( get_option( 'b4_useIncludedTemplate' ) ) {
    add_action( '404_template','bing404_use_included_template_hook' );
}

/**
 * include the standard template.
 *
 * If the user has opted to use the included template then include it for use.
 */
function bing404_use_included_template_hook() {
    include dirname( __FILE__ ). '/default-404.php';
    exit;
}

/**
 * perform the actual search
 * 
 * This is the heart of the plugin. This function is called from the 404.php
 * template. No parameters are necessary. It returns the properly formatted
 * HTML to either display a list of potential links or an error message.
 *
 * @return string html to output.
 */
function bing404_search_bing() {
    include 'Msft/Exception.php';
    include 'Msft/Bing/Exception.php';
    include 'Msft/Bing/Search/Exception.php';
    include 'Msft/Bing/Search.php';

    /*
     * Create the Bing Search object.
     */
    $search = new Msft_Bing_Search( get_option( 'b4_apiKey' ) );
    $search->setWebCount( get_option( 'b4_count' ) );
    $search->setSource( 'Web' );
    $search->setAdult( get_option( 'b4_adult' ) );

    /*
     * If requested, make this a site specific search
     */
    if ( $localsite = get_option( 'b4_site' ) ) {
        $search->setSite( $localsite );
    }

    /*
     * If set, set the local market
     */
    $localMarket = get_option( 'b4_market' );
    if ( !empty( $localMarket ) && $localMarket != 'NONE' ) {
        $search->setMarket( $localMarket );
    }

    /*
     * Build the query to execute
     */
    $queryTerms = str_replace( '/', ' ', html_entity_decode( urldecode( $_SERVER['REQUEST_URI'] ) ) );
    $localQuery = get_option( 'b4_query' );

    /*
     * Try to pull the site-wide from cache. Otherwise, pull from bing
     */
    $cacheKey = md5( $localQuery );
    $raw      = wp_cache_get( $cacheKey );

    if ( $raw === false ) {
        $search->setQuery( $localQuery );
        $raw = $search->search();
        wp_cache_set( $cacheKey, $raw,'',86400 );
    }

    $siteResults = json_decode( $raw );

    /*
     * Try to pull the regular query from cache. Otherwise, pull from bing
     */
    $localQuery = trim( $queryTerms );
    $cacheKey   = md5( $localQuery );
    $raw        = wp_cache_get( $cacheKey );

    if ( $raw === false ) {
        $search->setQuery( $localQuery );
        $raw = $search->search();
        wp_cache_set( $cacheKey, $raw,'',86400 );
    }
    $results = json_decode( $raw );

    /*
     * Now merge the resultsets
     */
    $finalResults = bing404_merge_results( $results, $siteResults );

    /*
     * Finally, prepare the output
     */
    $output = '<ol class="bing404">';
    foreach ( $finalResults as $value ) {
        $output .= sprintf( '<li><a href="%s">%s</a></li>', $value->Url, $value->Title );
    }

    $output .= '</ol>';
    $bing404_dirname = WP_PLUGIN_URL . '/' . ( basename( dirname( __FILE__ ) ) );

    switch ( get_option( 'b4_poweredByBing' ) ) {
        case 'Banner':
            $output .= '<div class="bing404_powerByBing"><a href="http://bing.com" target="_blank"><img src="'. $bing404_dirname . '/bing_powered.gif" /></a></div>';
            break;
        case 'Text':
            $output .= '<div class="bing404_powerByBing">Powered by <a href="http://bing.com" target="_blank">Bing</a></div>';
            break;
        case 'Off':
            break;
    }
    return $output;
}

/**
 * Merges two results sets. Takes the first one first and if it is shorter than
 * the currently requested max rows, it fills in with rows from the second one.
 * Helper function. This takes the results of the two searches and merges them
 * into a single array.
 *
 * @param array $local the results from the local search
 * 
 * @param array $site the results from the site wide search
 * 
 * @return array the combined resultset
 * 
 */
function bing404_merge_results( $local, $site ) {
    $returnValue  = array();
    $finalCounter = get_option( 'b4_count' );
    /*
     * Local
     */
    if ( isset ( $local->SearchResponse->Web->Results ) ) {
        foreach ( $local->SearchResponse->Web->Results as $value ) {
            if ( count( $returnValue ) < $finalCounter ) {
                $returnValue[] = $value;
            }
        }
    }

    if ( isset( $site->SearchResponse->Web->Results ) ) {
        foreach ( $site->SearchResponse->Web->Results as $value ) {
            if ( count( $returnValue ) < $finalCounter ) {
                $returnValue[] = $value;
            }
        }
    }
    return $returnValue;
} // function bing404_merge_results($local, $site)


/**
 * Outputs the HTML for the plugin options page.
 */
function bing404_options() {
    // BEGIN RAW HTML
?>
<div>
<h2>Bing 404 page options</h2>

<form action="options.php" method="post">
<?php settings_fields( 'bing404_options' ); ?>
<?php do_settings_sections( 'bing404' ); ?>
<br />
<input name="Submit" type="submit" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
</form></div>

<?php
    // END RAW HTML
    return;
} // function bing404_options()


/**
 * output the html necessary to allow the input of the api key
 */
function bing404_set_api_key() {
    $b4_keyPassedLocal = get_option( 'b4_apiKeyPassed' );
    $b4_keyPassedLocal = ( (bool)$b4_keyPassedLocal )?'true':'false';
    echo "<input id='b4_apiKeyPassed' id='b4_apiKeyPassed' name='b4_apiKeyPassed' type='hidden' value='".$b4_keyPassedLocal."' />";
    echo "<input id='b4_apiKey' name='b4_apiKey' size='60' onchange='bing404_checkKey(this)' type='text' value='".get_option( 'b4_apiKey' )."' />";
    echo '<span style="visibility: hidden; background:red;" id="apikey_warn">This appears to be an invalid API key.</span>';
}


/**
 * output the html necessary to allow the input of query prefix.
 */
function bing404_set_query() {
    echo "<input id='b4_query' name='b4_query' size='60' type='text' value='".get_option( 'b4_query' )."' />";
}


/**
 * output the html necessary to allow the input of adult filter option
 */
function bing404_set_adult() {
    $localAdult = get_option( 'b4_adult' );
    echo '<select id="b4_adult" name="b4_adult">';
    echo '<option '.($localAdult == 'Strict'?'SELECTED':'').'>Strict</option>';
    echo '<option '.($localAdult == 'Moderate'?'SELECTED':'').'>Moderate</option>';
    echo '<option '.($localAdult == 'Off'?'SELECTED':'').'>Off</option>';
    echo '</select>';
}


/**
 * output the html necessary to allow the input of the site specific filter
 */
function bing404_set_site() {
    echo "<input id='b4_site' name='b4_site' size='60' type='text' value='".get_option( 'b4_site' )."' />";
}


/**
 * output the html necessary to allow the input of the number of results to
 * return
 */
function bing404_set_count() {
    echo "<input id='b4_count' name='b4_count' size='5' type='text' value='".get_option( 'b4_count' )."' />";
}


/**
 * output the html necessary to allow the selection of the default template
 */
function bing404_set_use_included_template() {
    $localUseIncludedTemplate = get_option( 'b4_useIncludedTemplate', 'on' );
    /*
     * uber kludge! for some reason it's not honoring the default option. So if
     * the api key doesn't not exist, we'll assume this is the first time
     * through and set this flag. (stupid WordPress...should work.)
     */
    $localFirstTimeFlag = ( get_option( 'b4_apiKey' )?false:true );
    if ( $localFirstTimeFlag && empty( $localUseIncludedTemplate ) ) {
        $localUseIncludedTemplate = 'on';
    }
    echo "<input id='b4_useIncludedTemplate' name='b4_useIncludedTemplate' type='checkbox' ".($localUseIncludedTemplate?'CHECKED':'')." />";
}

/**
 * output the html necessary to allow the selection of the default market
 */
function bing404_set_market() {
    $localMarket   = get_option( 'b4_market' );
    $marketOptions = bing404_get_market_options();

    echo '<select name="b4_market">';

    foreach ( $marketOptions as $key=>$value )
    {
        echo '<option value="'.$key.'"'.($key == $localMarket?'SELECTED':'').'>'.$value.'</option>'."\n";
    }

    echo '</select>';
    return;
}


/**
 * output the html necessary to allow the selection of PowerByBing option
 */
function bing404_set_powered() {
    $localPowered = get_option( 'b4_poweredByBing' );
    echo '<select id="b4_poweredByBing" name="b4_poweredByBing">';
    echo '  <option '.($localPowered == 'Banner'?'SELECTED':'').'>Banner</option>';
    echo '  <option '.($localPowered == 'Text'?'SELECTED':'').'>Text</option>';
    echo '  <option '.($localPowered == 'Off'?'SELECTED':'').'>Off</option>';
    echo '</select>';
}


/**
 * outputs the default section description
 */
function bing404_section_text() {
    echo 'These are the main settings for the Bing 404 Error handler page';
}


/**
 * register the variables to be edited on the options page
 */
function bing404_setup_admin() {
    register_setting( 'bing404_options', 'b4_apiKey' );
    register_setting( 'bing404_options', 'b4_adult' );
    register_setting( 'bing404_options', 'b4_site' );
    register_setting( 'bing404_options', 'b4_count' );
    register_setting( 'bing404_options', 'b4_market' );
    register_setting( 'bing404_options', 'b4_apiKeyPassed' );
    register_setting( 'bing404_options', 'b4_query' );
    register_setting( 'bing404_options', 'b4_poweredByBing' );
    register_setting( 'bing404_options', 'b4_useIncludedTemplate' );
    add_settings_section( 'bing404_main', 'Main Settings', 'bing404_section_text', 'bing404' );
    add_settings_field( 'b4_apiKey', 'Bing API Key', 'bing404_set_api_key', 'bing404','bing404_main' );
    add_settings_field( 'b4_query', 'Predefined Search. If you want to narrow your search beyond just your domain, you can put a query here.', 'bing404_set_query', 'bing404', 'bing404_main' );
    add_settings_field( 'b4_adult', 'Adult Search Setting', 'bing404_set_adult', 'bing404','bing404_main' );
    add_settings_field( 'b4_site', 'Site Speficic Search. This is the domain that the search will be limited to. (We highly recommended that you use your domain)', 'bing404_set_site', 'bing404', 'bing404_main' );
    add_settings_field( 'b4_count', 'Number of rows to return', 'bing404_set_count', 'bing404', 'bing404_main' );
    add_settings_field( 'b4_market', 'Geographical area to limit search results from.', 'bing404_set_market', 'bing404', 'bing404_main' );
    add_settings_field( 'b4_useIncludedTemplate', 'Use the 404 template included with the plugin. (If you uncheck this, you need to make sure that your template has a 404 template and that you have properly modified it)', 'bing404_set_use_included_template', 'bing404', 'bing404_main' );
    add_settings_field( 'b4_poweredByBing', 'Display "Powered By Bing" ', 'bing404_set_powered', 'bing404', 'bing404_main' );
    return;
}


/**
 * register the options page witht he admin menu
 */
function bing404_admin_add_page() {
    $settingsPage = add_options_page( 'Bing 404 Options Page', 'Bing 404', 'manage_options', 'bing404', 'bing404_options' );
    add_action( 'admin_head-'.$settingsPage, 'bing404_add_keycheck_javascript' );
}


/**
 * ajax callback to check the validity of the apiKey
 */
function bing404_key_check_callback() {
        include 'Msft/Bing/Search.php';
	global $wpdb; // this is how you get access to the database
	$apiKey = $_POST['apiKey'];
        $keycheck = new Msft_Bing_Search( $apiKey );
        $keycheck->setQuery( 'Microsoft' );
        $resutls = $keycheck->search();
        echo $resutls;
	die();
}



/**
 * Javascript to be added to the options page to check the apiKey
 */
function bing404_add_keycheck_javascript() {
    // BEGIN JavaScript
?>
<script type="text/javascript" >


function bing404_checkKey(control) {
    	var data = {
		action: 'bing404_keyAction',
		apiKey: control.value
	};

	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.post(ajaxurl, data, bing404_processResponse);
    return;
}


function bing404_processResponse(rawResponse) {
    response=eval('(' + rawResponse + ')')

    try {
        errorCode =response.SearchResponse.Errors[0].Code; 
    } catch (ex) {
        errorCode=0;
    }
    
    if (errorCode>0) {
        document.getElementById('apikey_warn').style.visibility = 'visible';
        bing404_keyChecked = true;
    } else {
        document.getElementById('apikey_warn').style.visibility = 'hidden';
        document.getElementsByName('b4_apiKeyPassed')[0].value = 'true';
    }
    
    //if (typeof(response.SearchResponse.Errors[0].Code));
    //alert(SearchResponse);
    //  alert(response);
}
</script>
<?php
    // END JavaScript
    return;
}


/**
 * builds an array of valid market types as listed on
 * http://msdn.microsoft.com/en-us/library/dd251064.aspx
 *
 * @returns array list of markets.
 */
function bing404_get_market_options() {
    $marketOptions = array();
    $marketOptions['NONE']  = 'None';
    $marketOptions['ar-XA'] = 'Arabic - Arabia';
    $marketOptions['bg-BG'] = 'Bulgarian - Bulgaria';
    $marketOptions['cs-CZ'] = 'Czech - Czech Republic';
    $marketOptions['da-DK'] = 'Danish - Denmark';
    $marketOptions['de-AT'] = 'German - Austria';
    $marketOptions['de-CH'] = 'German - Switzerland';
    $marketOptions['de-DE'] = 'German - Germany';
    $marketOptions['el-GR'] = 'Greek - Greece';
    $marketOptions['en-AU'] = 'English - Australia';
    $marketOptions['en-CA'] = 'English - Canada';
    $marketOptions['en-GB'] = 'English - United Kingdom';
    $marketOptions['en-ID'] = 'English - Indonesia';
    $marketOptions['en-IE'] = 'English - Ireland';
    $marketOptions['en-IN'] = 'English - India';
    $marketOptions['en-MY'] = 'English - Malaysia';
    $marketOptions['en-NZ'] = 'English - New Zealand';
    $marketOptions['en-PH'] = 'English - Philippines';
    $marketOptions['en-SG'] = 'English - Singapore';
    $marketOptions['en-US'] = 'English - United States';
    $marketOptions['en-XA'] = 'English - Arabia';
    $marketOptions['en-ZA'] = 'English - South Africa';
    $marketOptions['es-AR'] = 'Spanish - Argentina';
    $marketOptions['es-CL'] = 'Spanish - Chile';
    $marketOptions['es-ES'] = 'Spanish - Spain';
    $marketOptions['es-MX'] = 'Spanish - Mexico';
    $marketOptions['es-US'] = 'Spanish - United States';
    $marketOptions['es-XL'] = 'Spanish - Latin America';
    $marketOptions['et-EE'] = 'Estonian - Estonia';
    $marketOptions['fi-FI'] = 'Finnish - Finland';
    $marketOptions['fr-BE'] = 'French - Belgium';
    $marketOptions['fr-CA'] = 'French - Canada';
    $marketOptions['fr-CH'] = 'French - Switzerland';
    $marketOptions['fr-FR'] = 'French - France';
    $marketOptions['he-IL'] = 'Hebrew - Israel';
    $marketOptions['hr-HR'] = 'Croatian - Croatia';
    $marketOptions['hu-HU'] = 'Hungarian - Hungary';
    $marketOptions['it-IT'] = 'Italian - Italy';
    $marketOptions['ja-JP'] = 'Japanese - Japan';
    $marketOptions['ko-KR'] = 'Korean - Korea';
    $marketOptions['lt-LT'] = 'Lithuanian - Lithuania';
    $marketOptions['lv-LV'] = 'Latvian - Latvia';
    $marketOptions['nb-NO'] = 'Norwegian - Norway';
    $marketOptions['nl-BE'] = 'Dutch - Belgium';
    $marketOptions['nl-NL'] = 'Dutch - Netherlands';
    $marketOptions['pl-PL'] = 'Polish - Poland';
    $marketOptions['pt-BR'] = 'Portuguese - Brazil';
    $marketOptions['pt-PT'] = 'Portuguese - Portugal';
    $marketOptions['ro-RO'] = 'Romanian - Romania';
    $marketOptions['ru-RU'] = 'Russian - Russia';
    $marketOptions['sk-SK'] = 'Slovak - Slovak Republic';
    $marketOptions['sl-SL'] = 'Slovenian - Slovenia';
    $marketOptions['sv-SE'] = 'Swedish - Sweden';
    $marketOptions['th-TH'] = 'Thai - Thailand';
    $marketOptions['tr-TR'] = 'Turkish - Turkey';
    $marketOptions['uk-UA'] = 'Ukrainian - Ukraine';
    $marketOptions['zh-CN'] = 'Chinese - China';
    $marketOptions['zh-HK'] = 'Chinese - Hong Kong SAR';
    $marketOptions['zh-TW'] = 'Chinese - Taiwan';
    return $marketOptions;
}
