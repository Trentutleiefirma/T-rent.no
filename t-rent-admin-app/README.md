# T-Rent Admin App

Mobilvennlig front-end for å administrere T-Rent WooCommerce uten å bruke wp-admin.

## Versjon 0.1.0

Første sikre MVP:

- egen appadresse: `/t-rent-app/`
- krever innlogget WordPress-bruker med WooCommerce-produktrettigheter
- produktsøk og produktliste
- endre produktnavn
- endre publiseringsstatus
- endre ordinær WooCommerce-grunnpris for ikke-RnB-produkter
- se RnB-produktets grunnpris, men RnB-leiepris er låst
- slå depositum av/på
- endre depositumbeløp
- mobilvennlig grensesnitt
- REST-kall beskyttet med WordPress REST nonce
- noindex/nofollow og ingen cache på app-siden

## Verifisert mot t-rent.no

Før koden ble laget ble den aktive installasjonen kontrollert:

- WooCommerce 11.1.2
- WooCommerce Rental & Booking (RnB) 18.0.3
- Deposits & Partial Payments for WooCommerce 1.2.13
- RnB-produkttype: `redq_rental`
- depositumfeltene på faktiske T-Rent-produkter:
  - `_awcdp_deposit_enabled`
  - `_awcdp_deposit_type`
  - `_awcdp_deposits_deposit_amount`

## Viktig

RnB sin faktiske leiepris styres av egne inventory/prisdata. Derfor er prisredigering for `redq_rental` bevisst deaktivert i v0.1.0.

Neste modul bør kartlegge og legge til:

1. RnB leiepris 1 dag / flerdagerspriser
2. kalender og manuell blokkering
3. ordre og forespørsler
4. utstyrskontroll/service
5. PWA/installasjon på mobilens hjemskjerm

## Installasjon

Ikke kopier til live før branchen er gjennomgått. Når den er godkjent kopieres hele mappen `t-rent-admin-app` til `wp-content/plugins/` og pluginet aktiveres i WordPress. Aktivering oppretter rewrite-regelen for `/t-rent-app/`.
