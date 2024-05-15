<?php

function lisaaTili($formdata, $baseurl='') {

    // Tuodaan funktiot, joilla voidaan lisätä henkilön tiedot kantaan.
    require_once(MODEL_DIR . 'henkilo.php');

    // Alustetaan virhetaulukko.
    $error = [];

    // Seuraavaksi tehdään lomaketietojen tarkistus.
    // Jos kentän arvo ei täytä tarkistuksen ehtoja,
    // niin error-taulukkoon lisätään virheen kuvaus.
    // Lopussa error-taulukko on tyhjä, jos kaikki kentät menivät tarkistuksesta lävitse.

    // Onko nimi määritelty...
    if (!isset($formdata['nimi']) || !$formdata['nimi']) {
        $error['nimi'] = "Anna nimesi.";
    } else {
        // ...ja täyttääkö se ehdot.
        if (!preg_match("/^[- '\p{L}]+$/u", $formdata["nimi"])) {
            $error['nimi'] = "Syötä nimesi ilman erikoismerkkejä.";
        }
    }

    // Onko discord-tunnus määritelty...
    if (!isset($formdata['discord']) || !$formdata['discord']) {
        $error['discord'] = "Anna discord-tunnuksesi muodossa tunnus#0000.";
    } else {
        // ...ja onko se muodossa tunnus#0000.
        if (!preg_match("/^.+#\d{4}$/",$formdata['discord'])) {
            $error['discord'] = "Discord-tunnuksesi muoto on virheellinen.";
        }
    }

    // Onko email määritelty...
    if (!isset($formdata['email']) || !$formdata['email']) {
        $error['email'] = "Anna sähköpostiosoitteesi.";
    } else {
        // ...ja onko se oikeassa muodossa...
        if (!filter_var($formdata['email'], FILTER_VALIDATE_EMAIL)) {
            $error['email'] = "Sähköpostiosoite on virheellisessä muodossa.";
        } else {
            // ...eikä vielä käytössä.
            if (haeHenkiloSahkopostilla($formdata['email'])) {
                $error['email'] = "Sähköpostiosoite on jo käytössä.";
            }
        }
    }

    // Onko molemmat salasanat annettu...
    if (isset($formdata['salasana1']) && $formdata['salasana1'] && isset($formdata['salasana2']) && $formdata['salasana2']) {
        // ...ja ovatko ne keskenään samat.
        if ($formdata['salasana1'] != $formdata['salasana2']) {
            $error['salasana'] = "Salasanasi eivät olleet samat!";
        }
    } else {
        $error['salasana'] = "Syötä salasanasi kahteen kertaan.";
    }

    // Lisätään tiedot tietokantaan, jos error-taulukko on tyhjä.
    if (!$error) {

        // Haetaan lomakkeen tiedot omiin muuttujiinsa.
        $nimi = $formdata['nimi'];
        $email = $formdata['email'];
        $discord = $formdata['discord'];
        // Salataan salasana samalla.
        $salasana = password_hash($formdata['salasana1'], PASSWORD_DEFAULT);

        // Lisätään henkilö tietokantaan.
        // Jos lisäys onnistui, tulee palautusarvona lisätyn henkilön id-tunniste.
        $idhenkilo = lisaaHenkilo($nimi,$email,$discord,$salasana);

        // Palautetaan JSON-tyyppinen taulukko, jossa:
        //  status   = Koodi, joka kertoo lisäyksen onnistumisen.
        //             Hyvin samankaltainen kuin HTTP-protokollan
        //             vastauskoodi.
        //             200 = OK
        //             400 = Bad Request
        //             500 = Internal Server Error
        //  id       = Lisätyn rivin id-tunniste.
        //  data     = Lisättävän henkilön lomakedata. Sama, mitä
        //             annettiin syötteenä.
        //  error    = Taulukko, jossa on lomaketarkistuksessa
        //             esille tulleet virheet.

        // Tarkistetaan onnistuiko henkilön tietojen lisääminen.
        if ($idhenkilo) {
            // Luodaan käyttäjälle aktivointiavain ja muodostetaan aktivointilinkki.
            require_once(HELPERS_DIR . "secret.php");
            $avain = generateActivationCode($email);
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $baseurl . "/vahvista?key=$avain";

            // Päivitetään aktivointiavain tietokantaan ja lähetetään käyttäjälle sähköpostia.
            if (paivitaVahvavain($email,$avain) && lahetaVahvavain($email,$url)) {
                // Onnistui, palautetaan tieto tilin onnistuneesta luomisesta.
                return [
                "status" => 200,
                "id"     => $idhenkilo,
                "data"   => $formdata
                ];
            } else {
                // Muuten palautetaan virhekoodi, joka ilmoittaa, että jokin lisäyksessä epäonnistui.
                return [
                "status" => 500,
                "data"   => $formdata
                ];
            }
        } else {
            return [
                "status" => 500,
                "data"   => $formdata
            ];
        }

    } else {

        // Error taulu ei ollut tyhjä,
        // palautetaan virheet ja muu data.
        return [
            "status" => 400,
            "data"   => $formdata,
            "error"  => $error
        ];

    }
}

function lahetaVahvavain($email,$url) {
    $message = "Hei!\n\n" . 
        "Olet rekisteröitynyt Lanify-palveluun tällä\n" . 
        "sähköpostiosoitteella. Klikkaamalla alla olevaa\n" . 
        "linkkiä vahvistat käyttämäsi sähköpostiosoitteen\n" .
        "ja pääset käyttämään Lanify-palvelua.\n\n" . 
        "$url\n\n" .
        "Jos et ole rekisteröitynyt Lanify palveluun, niin\n" . 
        "silloin tämä sähköposti on tullut sinulle\n" .
        "vahingossa. Siinä tapauksessa ole hyvä ja\n" .
        "poista tämä viesti.\n\n".
        "Terveisin, Lanify-palvelu";
    return mail($email,'Lanify-tilin aktivointilinkki',$message);
}  

?>