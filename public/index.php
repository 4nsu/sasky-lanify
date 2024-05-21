<?php

// Aloitetaan istunnot.
session_start();

// Suoritetaan projektin alustusskripti.
require_once '../src/init.php';

// Siistitään polku urlin alusta ja mahdolliset parametrit urlin lopusta.
// Siistimisen jälkeen osoite /~akoivu/lanify/tapahtuma?id=1 on 
// lyhentynyt muotoon /tapahtuma.
$request = str_replace($config['urls']['baseUrl'],'',$_SERVER['REQUEST_URI']);
$request = strtok($request, '?');

// Luodaan uusi Plates-olio ja kytketään se sovelluksen sivupohjiin.
$templates = new League\Plates\Engine(TEMPLATE_DIR);

// Haetaan kirjautuneen käyttäjän tiedot,
// eli katsotaan onko käyttäjä kirjautunut.
if (isset($_SESSION['user'])) {
    require_once MODEL_DIR . 'henkilo.php';
    $loggeduser = haeHenkilo($_SESSION['user']);
} else {
    $loggeduser = NULL;
}

// Kutsukäsittelijän ehtolause.
// Selvitetään mitä sivua on kutsuttu ja suoritetaan sivua vastaava käsittelijä.
switch ($request) {
    case '/':
    case '/tapahtumat':
        require_once MODEL_DIR . 'tapahtuma.php';
        $tapahtumat = haeTapahtumat();
        echo $templates->render('tapahtumat', ['tapahtumat' => $tapahtumat]);
        break;
    case '/tapahtuma':
        require_once MODEL_DIR . 'tapahtuma.php';
        require_once MODEL_DIR . 'ilmoittautuminen.php';
        $tapahtuma = haeTapahtuma($_GET['id']);
        if ($tapahtuma) {
            if ($loggeduser) {
                $ilmoittautuminen = haeIlmoittautuminen($loggeduser['idhenkilo'],$tapahtuma['idtapahtuma']);
            } else {
                $ilmoittautuminen = NULL;
            }
            echo $templates->render('tapahtuma', ['tapahtuma' => $tapahtuma, 'ilmoittautuminen' => $ilmoittautuminen, 'loggeduser' => $loggeduser]);
        } else {
            echo $templates->render('tapahtumanotfound');
        }
        break;
    case '/lisaa_tili':
        if (isset($_POST['laheta'])) {
            $formdata = cleanArrayData($_POST);
            require_once CONTROLLER_DIR . 'tili.php';
            $tulos = lisaaTili($formdata, $config['urls']['baseUrl']);
            if ($tulos['status'] == "200") {
                echo $templates->render('tili_luotu', ['formdata' => $formdata]);
                break;
            }
            echo $templates->render('lisaa_tili', ['formdata' => $formdata, 'error' => $tulos['error']]);
            break;
        } else {
            echo $templates->render('lisaa_tili', ['formdata' => [], 'error' => []]);
            break;
        }
    case '/kirjaudu':
        if (isset($_POST['laheta'])) {
            require_once CONTROLLER_DIR . 'kirjaudu.php';
            if (tarkistaKirjautuminen($_POST['email'],$_POST['salasana'])) {
                require_once MODEL_DIR . 'henkilo.php';
                $user = haeHenkilo($_POST['email']);
                if ($user['vahvistettu']) {
                    session_regenerate_id();
                    $_SESSION['user'] = $user['email'];
                    $_SESSION['admin'] = $user['admin'];
                    header("Location: " . $config['urls']['baseUrl']);
                } else {
                    echo $templates->render('kirjaudu', [ 'error' => ['virhe' => 'Tili on vahvistamatta! Ole hyvä, ja vahvista tili sähköpostissa olevalla linkillä.']]);
                }
            } else {
                echo $templates->render('kirjaudu', [ 'error' => ['virhe' => 'Väärä käyttäjätunnus tai salasana!']]);
            }
        } else {
            echo $templates->render('kirjaudu', [ 'error' => []]);
        }
        break;
    case "/logout":
        require_once CONTROLLER_DIR . 'kirjaudu.php';
        logout();
        header("Location: " . $config['urls']['baseUrl']);
        break;
    case '/ilmoittaudu':
        if ($_GET['id']) {
            require_once MODEL_DIR . 'ilmoittautuminen.php';
            $idtapahtuma = $_GET['id'];
            if ($loggeduser) {
                lisaaIlmoittautuminen($loggeduser['idhenkilo'],$idtapahtuma);
            }
            header("Location: tapahtuma?id=$idtapahtuma");
        } else {
            header("Location: tapahtumat");
        }
        break;
    case '/peru':
        if ($_GET['id']) {
            require_once MODEL_DIR . 'ilmoittautuminen.php';
            $idtapahtuma = $_GET['id'];
            if ($loggeduser) {
                poistaIlmoittautuminen($loggeduser['idhenkilo'],$idtapahtuma);
            }
            header("Location: tapahtuma?id=$idtapahtuma");
        } else {
            header("Location: tapahtumat");  
        }
        break;
    case "/vahvista":
        if(isset($_GET['key'])) {
            $key = $_GET['key'];
            require_once MODEL_DIR . 'henkilo.php';
            if (vahvistaTili($key)) {
                echo $templates->render('tili_aktivoitu');
            } else {
                echo $templates->render('tili_aktivointi_virhe');
            }
        } else {
            header("Location: " . $config['urls']['baseUrl']);
        }
        break;
    case "/tilaa_vaihtoavain":
        $formdata = cleanArrayData($_POST);
        // Tarkistetaan onko lomake lähetetty.
        if (isset($formdata['laheta'])) {
            require_once MODEL_DIR . 'henkilo.php';
            // Tarkistetaan onko lomakkeelle syötetty käyttäjätili olemassa.
            $user = haeHenkilo($formdata['email']);
            if ($user) {
                // Käyttäjätili olemassa, luodaan salasanan vaihtolinkki ja lähetetään se.
                require_once CONTROLLER_DIR . 'tili.php';
                $tulos = luoVaihtoavain($formdata['email'], $config['urls']['baseUrl']);
                if ($tulos['status'] == "200") {
                    // Vaihtolinkki lähetetty sähköpostiin.
                    echo $templates->render('tilaa_vaihtoavain_lahetetty');
                    break;
                }
                // Vaihtolinkin lähetyksessä virhe.
                echo $templates->render('virhe');
                break;
            } else {
                // Tunnuksella ei käyttäjätiliä, turvallisuus syistä tulostetaan silti summittainen ilmoitus.
                echo $templates->render('tilaa_vaihtoavain_lahetetty');
                break;
            }
        } else {
            // Lomaketta ei ole lähetetty, tulostetaan lomake.
            echo $templates->render('tilaa_vaihtoavain_lomake');
        }
        break;
    case "/reset":
        // Otetaan vaihtoavain talteen.
        $resetkey = $_GET['key'];

        // Tarkistetaan onko vaihtoavain olemassa, ja vielä aktiivinen.
        require_once MODEL_DIR . 'henkilo.php';
        $rivi = tarkistaVaihtoavain($resetkey);
        if ($rivi) {
            // Vaihtoavain löytyi, tarkistetaan onko se vanhentunut.
            if ($rivi['aikaikkuna'] < 0) {
                echo $templates->render('reset_virhe');
                break;
            }
        } else {
            echo $templates->render('reset_virhe');
            break;
        }

        // Vaihtoavain on voimassa, tarkistetaan onko lomakkeen kautta syötetty tietoa.
        $formdata = cleanArrayData($_POST);
        if (isset($formdata['laheta'])) {
            // Lomakkeelle syötetyt uudet salasanat annetaan kontrollerin käsittelyyn.
            require_once CONTROLLER_DIR . 'tili.php';
            $tulos = resetoiSalasana($formdata,$resetkey);
            // Tarkistetaan resetoinnin lopputulos.
            if ($tulos['status'] == "200") {
                // Salasana vaihdettu.
                echo $templates->render('reset_valmis');
                break;
            }
            // Salasanan vaihdossa virheitä.
            echo $templates->render('reset_lomake',['error' => $tulos['error']]);
        } else {
            // Lomakkeen tietoja ei ole vielä täytetty, tulostetaan lomake.
            echo $templates->render('reset_lomake', ['error' => '']);
            break;
        }
        
        break;      
    default:
        echo $templates->render('notfound');
}

?>