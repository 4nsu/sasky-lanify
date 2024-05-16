<?php $this->layout('template', ['title' => 'Kirjautuminen']) ?>

<h1>Kirjautuminen</h1>

<!-- Varmistetaan että kirjautumistiedot lähetetää aina HTTPS-protokollan kautta,
eli form action arvona kirjautumissivun tarkka osoite.
Ei välttämätöntä neutronilla, joka ohjaa pyynnöt aina HTTPS kautta. -->
<form action="https://<?= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>" method="POST">
    <div>
        <label>Sähköposti:</label>
        <input type="text" name="email">
    </div>
    <div>
        <label>Salasana:</label>
        <input type="password" name="salasana">
    </div>
    <div class="error"><?= getValue($error,'virhe'); ?></div>
    <div>
        <input type="submit" name="laheta" value="Kirjaudu">
    </div>
</form>

<div class="info">
    Jos sinulla ei ole vielä tunnuksia, niin voit luoda ne <a href="lisaa_tili">täällä</a>.<br>
    Jos olet unohtanut salasanasi, niin voit vaihtaa sen <a href="tilaa_vaihtoavain">täällä</a>.
</div>