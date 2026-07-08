# Freezer Monitor

This project monitors freezer temperature and creates alerts when readings are out of range.

## Installation

1. Clone the repository into your XAMPP htdocs folder:
	- Example path: `c:\xampp\htdocs\freezer-monitor`
2. Create the database and tables:
	- Open phpMyAdmin and import `database/init.sql`
	- If your database already exists from a previous version, also run `database/update_add_door_tracking.sql`
	- For existing installations, also run `database/update_add_calibration_factor.sql`
	- To enable SMS alerts, also run `database/update_add_sms_alarms.sql`
3. Configure environment variables in `.env`:
	- `DB_HOST=localhost`
	- `DB_NAME=freezer_monitor`
	- `DB_USER=root`
	- `DB_PASS=`
	- SMS via Teltonika modem (opcional):
		- `SMS_ENABLED=true`
		- `SMS_ALARM_MIN_MINUTES=60` (minutos consecutivos fora do intervalo antes do envio)
		- `MODEM_SCHEME=https`
		- `MODEM_HOST=192.168.63.253:8443`
		- `MODEM_USER=admin`
		- `MODEM_PASS=SEGREDO`
		- `MODEM_ID=3-1`
		- `MODEM_TIMEOUT=8`
		- `MODEM_VERIFY_SSL=false`
4. Start Apache and MySQL in XAMPP Control Panel.

## Usage

Access the app in one of these modes:

- Recommended (project root rewrite):
  - `http://localhost/freezer-monitor`
- Direct public entrypoint:
  - `http://localhost/freezer-monitor/public`

If needed, you can force a custom base URL by setting `BASE_URL` in `.env`.

## SMS de alarme

O sistema envia um SMS por cada dispositivo que fique com temperatura fora do
intervalo definido (`temp_min`/`temp_max`) durante mais do que
`SMS_ALARM_MIN_MINUTES` minutos consecutivos (por defeito 60). Quando a
temperatura regressa ao intervalo, e enviado um SMS de recuperacao `[OK]`.

- Envio feito atraves de um modem Teltonika (RutOS 7+, ex. TRB145) via API REST.
- Ligue `SMS_ENABLED=true` no `.env` e preencha as credenciais do modem.
- Corra `database/update_add_sms_alarms.sql` para criar as tabelas de estado e log.
- No painel de administracao em `Gestao de utilizadores`, preencha o numero de
  telefone e active a opcao **SMS** para cada utilizador que devera receber os
  alarmes.
- Todos os envios (sucesso, falha, ignorado) sao registados na tabela `sms_log`
  e em `storage/logs/sms_alarms.log`.

## License

This project is licensed under the MIT License.