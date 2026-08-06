-- Parallel feature tests give every worker a database of its own - db_1, db_2 and so on, see
-- SwooleBundle\SwooleBundle\Tests\Helper\TestToken. The MySQL entrypoint grants the test user nothing
-- beyond the "db" it creates, and creating a database is a privilege of its own.
--
-- In a GRANT the database name is a pattern, where an escaped underscore is a literal one and % matches
-- the rest - so this one statement covers the whole family, including the ones that do not exist yet.
-- With it in place the tests need no privileged account: the fixture user creates its own worker database
-- and already holds the rights on it.
GRANT ALL ON `db\_%`.* TO 'user'@'%';
FLUSH PRIVILEGES;
