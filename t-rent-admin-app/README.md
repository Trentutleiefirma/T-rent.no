# T-Rent Admin App

Mobilvennlig front-end for å administrere T-Rent uten å bruke wp-admin.

## Versjon 0.6.0

Appen samler nå fem hovedområder på `/t-rent-app/`:

### Forespørsler
- viser RnB `request_quote` direkte i appen
- **Godkjenn** setter RnB-status `quote-accepted`
- **Avslå** setter RnB-status `quote-cancelled`
- standard RnB-statusmail brukes ved godkjenning/avslag
- intern kommentar kan lagres separat uten å sende en ekstra kundemelding
- lagrede kommentarer vises igjen på forespørselen
- godkjente forespørsler viser betalingslenke når RnB sin checkout-side finnes

### Bookinger
- aktive, kommende og avsluttede RnB-bookinger
- produkt og ordrenummer
- **leie fra / til**
- **dato og klokkeslett bookingen ble opprettet**
- **kundenavn, telefon og e-post**
- ordrestatus, beløp og betalingsmåte
- telefonnummer er klikkbart på mobil
- e-post er klikkbar

### Blokker dato
Samme RnB-logikk som T-Rent Datoblokkering i «Utleie system»:
- velg utleieprodukt
- velg fra- og til-dato
- samme dato i begge felt = én hel blokkert dag
- blokkering skrives til `rnb_availability` som `CUSTOM`
- blokkering gjelder alle inventory-poster som er knyttet til produktet
- aktive blokkeringer kan fjernes fra appen
- WooCommerce/LiteSpeed produktcache tømmes etter endring

### Utstyrskontroll
Samme statusdata som T-Rent Utstyrskontroll:
- Må kontrolleres
- Service
- Ikke klar
- Ute på leie
- Kontrollert og klar
- sist kontrollert
- sist service
- siste / pågående leie
- neste booking
- notat kan lagres ved statusendring
- WooCommerce-retur gjør utstyret kontrollpliktig etter samme logikk som eksisterende kontroll-plugin

### Produkter
- produktlisten er trykkbar også på mobil
- på mobil åpnes produktredigering som egen visning med Tilbake-knapp
- opprett nytt RnB-utleieprodukt eller enkelt WooCommerce-produkt direkte i appen
- nye produkter opprettes som kladd som standard
- produktnavn og publiseringsstatus kan endres
- depositum av/på og depositumbeløp kan redigeres
- ordinær WooCommerce-grunnpris kan settes for ikke-RnB-produkter
- RnB-leiepris og inventory/prisdata endres foreløpig ikke automatisk ved oppretting

## Sikkerhet
- krever innlogget WordPress-bruker med WooCommerce-produktrettigheter
- REST-kall bruker WordPress REST nonce
- app-siden er noindex/nofollow og no-cache
- ingen WooCommerce- eller RnB-kjernefiler endres av appen

## Installering
Denne branchen skal gjennomgås før live-installasjon. Når den er godkjent kopieres hele mappen `t-rent-admin-app` til `wp-content/plugins/`, pluginet aktiveres og appen åpnes på `/t-rent-app/`.


## Felles søk
Ett søkefelt øverst følger aktiv fane:
- Bookinger: produkt, ordre, kunde, telefon, e-post, leiedato, bookingdato, status og betalingsmåte
- Forespørsler: produkt, kunde, telefon, e-post, leiedato, status og interne kommentarer
- Blokkeringer: produkt, produkt-ID og dato
- Utstyr: navn, status, kontroll/service, notater, ordrenummer og bookingdatoer
- Produkter: produktnavn via WooCommerce-søket

Søket filtrerer bare visningen og endrer ingen data.


## Bookingstatus
T-Rent App har en egen driftsstatus som ikke endrer WooCommerce betalings-/ordrestatus:
- Pågående: aktiv leie
- På pause: kan settes manuelt når en booking må stoppes/endres
- Fullført: vises automatisk etter endt leie eller kan settes manuelt
- Kommende: booking som ikke har startet ennå

Status kan endres fra bookingkortet i appen og lagres på WooCommerce-ordren via WooCommerce CRUD.


## PWA
T-Rent Admin App kan installeres som en egen PWA på mobil:
- egen app-identitet på `/t-rent-app/`, separat fra eventuell eksisterende T-Rent-PWA
- navn: T-RENT APP
- standalone-visning uten vanlig nettleserlinje
- egen manifest og service worker
- service worker bruker ikke offline-cache av bookinger, kundeinformasjon eller andre private appdata
- «Installer app»-knappen vises når nettleseren tilbyr installasjon


## Endring i 0.4.0
Forespørsler er lagt inn som egen appfane og er isolert fra RnB-kjernefilene. Appen bruker RnB sine registrerte forespørselsstatuser og e-postklasser, mens interne T-Rent-kommentarer lagres som egen post-meta på forespørselen.


## PWA-fiks i 0.4.2
Appikonene hentes nå direkte fra WordPress sitt konfigurerte Site Icon i 192x192, 180x180 og 512x512. De tidligere binære JPG-filene i pluginen er fjernet fordi de var korrupte. Manifestet har fortsatt `prefer_related_applications=false`.


## Relevante forespørsler i 0.4.4
Standardfilteret «Aktuelle forespørsler» viser bare åpne forespørsler som fortsatt trenger handling:
- status er venter på svar, behandles eller på vent
- det finnes ikke allerede en RnB/WooCommerce-ordre for forespørselen
- leieperioden er ikke utløpt
- helt nye forespørsler uten lesbare leiedatoer beholdes i opptil 7 dager som sikkerhetsnett

Gamle eller allerede konverterte forespørsler kan fortsatt finnes under «Alle», men vises ikke lenger i standardlisten.


## Endring i 0.5.0
- fanen «Utstyr» heter nå «Utstyrskontroll»
- produktkort er ekte trykkbare knapper
- mobilvisningen skjuler den lange produktlisten når et produkt åpnes, slik at redigeringen vises med en gang
- «Nytt produkt» oppretter WooCommerce-produkt via appens sikrede REST-endepunkt
- produkttype kan være RnB utleie eller enkelt produkt


## Endring i 0.6.0
Produktdelen er bygget om slik at en komplett WooCommerce/RnB-annonse kan lages fra mobilen:
- produktnavn, status, SKU og URL-navn
- kort beskrivelse og full annonsetekst
- hovedbilde og produktgalleri med bildeopplasting
- produktkategorier og etiketter
- RnB-utstyr/lager opprettes automatisk eller kobles til eksisterende inventory
- grunnpris per dag og prisnivåer etter antall leiedager
- RnB forespørsel/direkte booking
- depositum av/på, type og beløp
- ordinær pris og lagerstatus for enkle WooCommerce-produkter
- lenke til både ferdig annonse og full WooCommerce-editor for eksisterende produkter

RnB-prisnivåer som angis som dagpris lagres som RnBs egne prosentbaserte leierabatter mot grunnprisen. Koden ligger i app-pluginen og endrer ikke RnB-kjernefiler.
