# T-Rent Ordreinformasjon 1.1.1

Et komplett, separat WordPress-tillegg. Installer hele ZIP-filen via **Utvidelser → Legg til ny → Last opp utvidelse**, og aktiver **T-Rent Ordreinformasjon**. Det skal ikke limes inn i RnB, `functions.php` eller `Email.php`.

## Hva tillegget gjør

Den faste informasjonen legges inn før ordresammendraget i kundens WooCommerce-mail `customer_processing_order` («under behandling»). Den vises også når ingen produkter har `maskin_info`, eller et produkt er slettet. Innholdet er henting foran låven til høyre, tilbakelevering innen kl. 20 siste leiedag, åpningstider, avtalt henting kvelden før, den tidligere teksten «Du finner brukerinformasjon og tips til utstyret lenger ned i denne e-posten», telefon og kartlenke.

Produktenes `maskin_info` vises separat etter ordresammendraget når feltet finnes. Som tidligere vises maskininformasjon i både «under behandling» og «fullført». Variantprodukter arver informasjon fra hovedproduktet når variantfeltet er tomt eller bare inneholder tom HTML som `<p></p>` eller `&nbsp;`. HTML og ren tekst støttes.

Fra versjon 1.1.0 lagres en privat kopi av `maskin_info` på nye ordrelinjer. Dermed viser en senere videresending eller «fullført»-mail den informasjonen som gjaldt da ordren ble opprettet, selv om produktet senere redigeres eller slettes. Eldre ordrer uten lagret kopi faller fortsatt tilbake til produktets nåværende informasjon.

Tillegget erstatter bare de kjente gamle callbackene `trent_processing_order_information` og `trent_vis_maskin_info_i_mail`, for å unngå doble blokker. Det endrer ikke pris, dato, betaling, depositum, kalender, ordrestatus, utsendingstriggere eller RnB-forespørsler. Det sender ikke e-post på egen hånd.

## Undersøkelsen

- Skjermbildet av ordre 851 viser at kundens «under behandling»-mail ble sendt, men uten fast informasjon før ordresammendraget.
- GitHub-arkivet `mftp_zip_2026_08_21_09_11_45.zip` inneholder en tilpasset mal: `construction-equipments/woocommerce/emails/customer-processing-order.php`. Den har den faste blokken uavhengig av produktmetadata. Temaet i arkivet er 1.5.5; aktivt tema rapporterte 1.5.8.
- Tidligere oppsett omtalte også en egen hook for henteinformasjonen. Verken den eller maskininfo-hooken finnes i de undersøkte PHP-filene på GitHub. Rettelsen i RnBs `Email.php` gjelder forespørsler, og gjenoppretter ikke WooCommerce-ordremalen.
- Dette viser at den tidligere informasjonsblokken ikke blir lagt inn i den aktuelle e-posten. En temaoppdatering er en mulig forklaring, men den konkrete hendelsen kan ikke bekreftes uten den aktive PHP-malen/snippetene eller endringslogg fra serveren.

## Kontroll og installasjonsstatus

Testfilen følger med ZIP-pakken under `tests/`. Den bruker ekte WordPress-hooks og formateringsfunksjoner samt testobjekter for WooCommerce-ordrer og produkter. Testen kontrollerer blant annet lagret maskininfo, slettede produkter, tom HTML på varianter, HTML/ren tekst, eldre ordrer og at ingen e-post sendes under testen.

Kjør fra WordPress-installasjonens rotmappe:

`php wp-content/plugins/t-rent-ordreinformasjon/tests/order-email-information-check.php wp-includes`

Etter aktivering: åpne WooCommerce sin forhåndsvisning av kundemailen «under behandling», og kontroller fast informasjon, ordrelinjer og eventuell maskininfo. Lag deretter en testordre med et variantprodukt og kontroller både «under behandling» og «fullført». Aktivering sender ikke gammel ordre 851 på nytt.

Deaktivering stopper visningen. Private kopier som allerede er lagret på ordrelinjer blir liggende, men brukes ikke og påvirker ikke ordren. Ikke gjenopprett den gamle temamalen i tillegg: den inneholder samme faste tekst direkte i malen og kan derfor gi en dobbel blokk. Ukjente kopier av gamle snippets må kontrolleres ved installasjon.
