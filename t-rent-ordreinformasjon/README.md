# T-Rent Ordreinformasjon 1.0.0

Et komplett, separat WordPress-tillegg. Installer hele ZIP-filen via **Utvidelser → Legg til ny → Last opp utvidelse**, og aktiver **T-Rent Ordreinformasjon**. Det skal ikke limes inn i RnB, `functions.php` eller `Email.php`.

## Hva som er rettet

Den faste informasjonen legges inn før ordresammendraget i kundens WooCommerce-mail `customer_processing_order` («under behandling»). Den vises også når ingen produkter har `maskin_info`, eller et produkt er slettet. Innholdet er henting foran låven til høyre, tilbakelevering innen kl. 20 siste leiedag, åpningstider, avtalt henting kvelden før, telefon og kartlenke.

Produktenes `maskin_info` vises separat etter ordresammendraget når feltet finnes. Som tidligere vises maskininformasjon i både «under behandling» og «fullført». Variantprodukter kan arve informasjon fra hovedproduktet. HTML og ren tekst støttes.

Tillegget erstatter bare de kjente gamle callbackene `trent_processing_order_information` og `trent_vis_maskin_info_i_mail`, for å unngå doble blokker. Det endrer ikke bestillinger, priser, datoer, betaling, depositum, kalender, utsendingstriggere eller RnB-forespørsler. Det sender ikke e-post på egen hånd.

## Undersøkelsen

- Skjermbildet av ordre 851 viser at kundens «under behandling»-mail ble sendt, men uten fast informasjon før ordresammendraget.
- GitHub-arkivet `mftp_zip_2026_08_21_09_11_45.zip` inneholder en tilpasset mal: `construction-equipments/woocommerce/emails/customer-processing-order.php`. Den har den faste blokken uavhengig av produktmetadata. Temaet i arkivet er 1.5.5; aktivt tema rapporterte 1.5.8.
- Tidligere oppsett omtalte også en egen hook for henteinformasjonen. Verken den eller maskininfo-hooken finnes i de undersøkte PHP-filene på GitHub. Rettelsen i RnBs `Email.php` gjelder forespørsler, og gjenoppretter ikke WooCommerce-ordremalen.
- Dette viser at den tidligere informasjonsblokken ikke blir lagt inn i den aktuelle e-posten. En temaoppdatering er en mulig forklaring, men den konkrete hendelsen kan ikke bekreftes uten den aktive PHP-malen/snippetene eller endringslogg fra serveren.

## Kontroll og installasjonsstatus

Koden testes separat med ekte WordPress-hooks og formateringsfunksjoner, og testobjekter for WooCommerce-ordrer og produkter. Dette erstatter ikke kontroll av en forhåndsvisning i den aktive nettbutikken.

Etter aktivering: åpne WooCommerce sin forhåndsvisning av kundemailen «under behandling», og kontroller fast informasjon, ordrelinjer og eventuell maskininfo. Aktivering sender ikke gammel ordre 851 på nytt. Tillegget kan deaktiveres for å rulle tilbake uten dataendringer.

Ikke gjenopprett den gamle temamalen i tillegg: den inneholder samme faste tekst direkte i malen og kan derfor gi en dobbel blokk. Ukjente kopier av gamle snippets må kontrolleres ved installasjon.

Test: `php tests/order-email-information-check.php /path/to/wordpress/wp-includes`
