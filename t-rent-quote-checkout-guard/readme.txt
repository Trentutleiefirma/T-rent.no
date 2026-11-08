=== T-Rent tilbudsvakt for kassen ===
Contributors: t-rent
Tags: woocommerce, rental, quote, checkout
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later

Krever et gyldig og godkjent RnB-tilbud før et utleieprodukt kan betales.

== Description ==

Utvidelsen lukker tre omveier rundt T-Rents forespørselsflyt:

* Direkte innlegging av et utleieprodukt i handlekurven uten tilbud.
* Gjenoppretting av en gammel eller ugyldig WooCommerce-handlekurv etter innlogging.
* RnBs offentlige tilbud-til-handlekurv-endepunkt når tilbudet ikke er godkjent,
  ikke tilhører produktet, allerede er brukt eller tilhører en annen innlogget kunde.

Godkjente tilbud, gjestebetaling fra tilbudslenken, sammenkoblede produkter og
RnBs funksjon for å forlenge en eksisterende ordre er fortsatt støttet.

== Changelog ==

= 1.0.1 =
* Respekter RnBs lokale «Book Now»-innstilling, slik at eksplisitte direktekjøpsprodukter ikke krever tilbud.
* Behold tilbudskravet for globale og lokale produkter uten aktivert direktekjøp.

= 1.0.0 =
* Krev status `quote-accepted` før RnB kan legge tilbudet i handlekurven.
* Valider tilbud, produkt, kunde og tidligere ordre på nytt ved kassen.
* Blokker og omdiriger en ugyldig vedvarende handlekurv etter innlogging.
* Støtt klassisk kasse og WooCommerce Cart/Checkout Blocks.
* Forklar tydelig at en registrert e-post krever innlogging og en ny innsending.
