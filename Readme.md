# Mysql Database Schema Fixer Tool

The Mysql Database Schema Fixer Tool is a PHP script designed to compare and synchronize the database schema between two environments (development and production). It helps identify missing tables, columns, triggers, and indexes in the development environment compared to the production environment. The tool generates SQL queries that can be used to fix the schema discrepancies.

## Prerequisites

To use the Mysql Database Schema Fixer Tool, ensure that you have the following:

- PHP installed on your system.
- Access to the development and production database environments.
- The necessary database credentials for both environments.

## Usage

1. Clone the repository:

   ```
   git clone https://github.com/TechJourneyer/mysql-database-schema-fixer.git
   ```

2. Navigate to the repository directory:

   ```
   cd mysql-database-schema-fixer
   ```

3. Rename `sample_config.php` to `config.php` and update the database credentials for the development and production environments:

   ```php
   $devCredentials = [
       'host' => 'dev-database-host',
       'database' => 'dev-database-name',
       'username' => 'dev-username',
       'password' => 'dev-password',
   ];

   $prodCredentials = [
       'host' => 'prod-database-host',
       'database' => 'prod-database-name',
       'username' => 'prod-username',
       'password' => 'prod-password',
   ];
   ```

4. Run the script:

   ```
   php index.php
   ```

5. The tool will perform the following actions:

   - Connect to the development and production databases.
   - Retrieve the list of tables from both databases.
   - Identify missing tables in the development database compared to the production database and export the results to a JSON file.
   - Get table details (columns, triggers, and indexes) for both environments and export the results to a JSON file.
   - Compare the schema between the environments and generate SQL queries for missing tables, columns, triggers, and indexes.
   - Store the generated SQL queries in a sql file .

6. Review the generated SQL file to see the queries that need to be executed to fix the schema discrepancies.

