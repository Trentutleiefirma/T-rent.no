=== T-Rent Bestillingskontroll ===
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Fornyer sikkerhetskoder fra en ny kopi av den samme offentlige produktsiden.
Brukes bare på RnB-utleieprodukter. Kundeopplysninger og valgte datoer beholdes.

Ved avvist nonce prøves prisberegning/lageroppslag maksimalt én gang til.
Ved gammel eller gjenopprettet fane fornyes kodene før forespørselen sendes.
En innsendt bestillingsforespørsel blir aldri automatisk sendt en gang til.
Også nonce-feltet for T-Rent Sammenkoblede Bookinger fornyes fra sidens HTML.
Ferskhet bekreftes med en unik forespørsels-ID; en gammel cachekopi godtas ikke.

WooPayments-sporingsskriptets manglende wcpayAssets-konfigurasjon gjenopprettes
på utleiesider, ut fra skriptets registrerte URL. Betalingsflyten endres ikke.

== Installer ==
Last opp ZIP-filen i Utvidelser > Legg til ny > Last opp utvidelse, og aktiver.
Cache Enabler tømmes én gang ved aktivering. Eksisterende åpne faner må lastes
på nytt én gang for å få den nye koden. Vanlig sidecache beholdes.

== Kontroll ==
WooCommerce > Bestillingskontroll viser maksimalt 50 nonce-avvisninger de siste
14 dagene. Avvisninger kan være automatisk rettet etterpå. Loggen inneholder
bare tidspunkt, AJAX-handling og produkt-ID. Den lagrer ikke IP-adresse,
navn, e-post, sikkerhetskoder, bestillingsinnhold eller nettleserhistorikk.

== Tilbakerulling ==
Deaktiver T-Rent Bestillingskontroll og tøm Cache Enabler. Den eksisterende
utleieutvidelsen er ikke overskrevet. En eventuell diagnoselogg er en enkelt
WordPress-option: trent_booking_health_events.
