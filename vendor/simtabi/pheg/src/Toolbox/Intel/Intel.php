<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Intel;

use Simtabi\Pheg\Toolbox\Intel\Info\Ip;
use Simtabi\Pheg\Toolbox\Intel\Info\Network;
use Simtabi\Pheg\Toolbox\Intel\Info\Os\Os;
use Simtabi\Pheg\Toolbox\Intel\Info\Request;
use Simtabi\Pheg\Toolbox\Intel\Info\Server;
use Simtabi\Pheg\Toolbox\Intel\Info\Ssl;
use Spatie\Url\Url;

final class Intel
{

    public function __construct() {}

    public function getIpInfo(): Ip
    {
        return new Ip();
    }

    public function getNetworkInfo(): Network
    {
        return new Network();
    }

    public function getRequestInfo(): Request
    {
        return new Request();
    }

    public function getServerInfo(): Server
    {
        return new Server();
    }

    public function getSslInfo(): Ssl
    {
        return new Ssl();
    }

    public function getOsInfo(): Os
    {
        return new Os();
    }

    public function getUrlInfo($url): Url
    {
        return Url::fromString($url);
    }

    public function isBot()
    {

    }

    public function getDeviceInfo()
    {

    }

    public function getUserAgent()
    {

    }

    public function getLocation()
    {

    }

    public function isHandHeldDevice()
    {

    }

    public function getDeviceType()
    {

    }

    public function getIP($getHostName = false) {

        // http://chriswiegman.com/2014/05/getting-correct-ip-address-php/
        //Just get the headers if we can or else use the SERVER global
        $hostname = null;
        if ( function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
        } else {
            $headers = $_SERVER;
        }

        //Get the forwarded IP if it exists
        if ( array_key_exists( 'X-Forwarded-For', $headers ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $realIp = $headers['X-Forwarded-For'];
        }
        elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )) {
            $realIp = $headers['HTTP_X_FORWARDED_FOR'];
        }
        else {
            if( (($_SERVER['HTTP_HOST']) === 'localhost') || (($_SERVER['SERVER_NAME']) === 'localhost') ){
                $hostname = 'localhost';
                $realIp   = '127.0.0.1';
            }
            elseif( (($_SERVER['HTTP_HOST']) === '127.0.0.1') || (($_SERVER['SERVER_NAME']) === '127.0.0.1') ){
                $hostname = 'localhost';
                $realIp   = '127.0.0.1';
            }
            else{
                $hostname = $_SERVER['SERVER_NAME'];
                $realIp   = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
            }
        }

        return $getHostName ? $hostname : $realIp;
    }

    public function getIpCountry($ip = null, $isoCode = true){

    }



    /**
     * Return the current URL.
     * @return string
     */
    public function getCurrentURL() {
        $url = '';
        if (self::isHttps()):
            $url.= 'https://';
        else:
            $url.= 'http://';
        endif;
        if (isset($_SERVER['PHP_AUTH_USER'])):
            $url.= $_SERVER['PHP_AUTH_USER'];
            if (isset($_SERVER['PHP_AUTH_PW'])):
                $url.= ':' . $_SERVER['PHP_AUTH_PW'];
            endif;
            $url.= '@';
        endif;

        $url.= $_SERVER['HTTP_HOST'];
        if ($_SERVER['SERVER_PORT'] != 80):
            $url.= ':' . $_SERVER['SERVER_PORT'];
        endif;
        if (!isset($_SERVER['REQUEST_URI'])):
            $url.= substr($_SERVER['PHP_SELF'], 1);
            if (isset($_SERVER['QUERY_STRING'])):
                $url.= '?' . $_SERVER['QUERY_STRING'];
            endif;
        else:
            $url.= $_SERVER['REQUEST_URI'];
        endif;
        return $url;
    }

    /**
     * Returns the IP address of the client.
     * @param   boolean $trustProxyHeaders Default false
     * @return  string
     */
    public function getClientIP($trustProxyHeaders = false) {
        if (!$trustProxyHeaders):
            return $_SERVER['REMOTE_ADDR'];
        endif;
        if (!empty($_SERVER['HTTP_CLIENT_IP'])):
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])):
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else:
            $ip = $_SERVER['REMOTE_ADDR'];
        endif;
        return $ip;
    }

    /**
     * Detect if user is on mobile device
     * @return boolean
     */
    public function isMobile() {
        $useragent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) OR preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))):
            return true;
        endif;
        return false;
    }

    /**
     * Get user browser
     * @return string
     */
    public function getBrowser() {
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
        $browserName = 'Unknown';
        $platform = 'Unknown';
        $version = "";
        if (preg_match('/linux/i', $u_agent)):
            $platform = 'Linux';
        elseif (preg_match('/macintosh|mac os x/i', $u_agent)):
            $platform = 'Mac OS';
        elseif (preg_match('/windows|win32/i', $u_agent)):
            $platform = 'Windows';
        endif;

        if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)):
            $browserName = 'Internet Explorer';
            $ub = "MSIE";
        elseif (preg_match('/Firefox/i', $u_agent)):
            $browserName = 'Mozilla Firefox';
            $ub = "Firefox";
        elseif (preg_match('/Chrome/i', $u_agent)):
            $browserName = 'Google Chrome';
            $ub = "Chrome";
        elseif (preg_match('/Safari/i', $u_agent)):
            $browserName = 'Apple Safari';
            $ub = "Safari";
        elseif (preg_match('/Opera/i', $u_agent)):
            $browserName = 'Opera';
            $ub = "Opera";
        elseif (preg_match('/Netscape/i', $u_agent)):
            $browserName = 'Netscape';
            $ub = "Netscape";
        endif;

        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $u_agent, $matches)) {
        }

        $i = count($matches['browser']);
        if ($i != 1):
            if (strripos($u_agent, "Version") < strripos($u_agent, $ub)):
                $version = $matches['version'][0];
            else:
                $version = $matches['version'][1];
            endif;
        else:
            $version = $matches['version'][0];
        endif;
        if ($version == null || $version == ""):
            $version = "?";
        endif;
        return implode(", ", array($browserName, "Version: " . $version, $platform));
    }

    /**
     * Get client location
     * @return mixed
     */
    public function getClientLocation() {
        $result = false;
        $ip_data = @json_decode(self::curl("http://www.geoplugin.net/json.gp?ip=" . self::getClientIP()));
        if (isset($ip_data) and $ip_data->geoplugin_countryName != null):
            $result = $ip_data->geoplugin_city . ", " . $ip_data->geoplugin_countryCode;
        endif;
        return $result;
    }


}