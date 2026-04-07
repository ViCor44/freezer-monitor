# Freezer Monitor

This project monitors freezer temperature and creates alerts when readings are out of range.

## Installation

1. Clone the repository into your XAMPP htdocs folder:
	- Example path: `c:\xampp\htdocs\freezer-monitor`
2. Create the database and tables:
	- Open phpMyAdmin and import `database/init.sql`
	- If your database already exists from a previous version, also run `database/update_add_door_tracking.sql`
3. Configure environment variables in `.env`:
	- `DB_HOST=localhost`
	- `DB_NAME=freezer_monitor`
	- `DB_USER=root`
	- `DB_PASS=`
4. Start Apache and MySQL in XAMPP Control Panel.

## Usage

Access the app in one of these modes:

- Recommended (project root rewrite):
  - `http://localhost/freezer-monitor`
- Direct public entrypoint:
  - `http://localhost/freezer-monitor/public`

If needed, you can force a custom base URL by setting `BASE_URL` in `.env`.

## License

This project is licensed under the MIT License.