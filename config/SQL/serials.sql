create table if not exists blu_insure.serials
(
    product_serialno         int auto_increment
        primary key,
    product_code             varchar(255) not null,
    product_network          varchar(255) not null,
    product_cover_amount     decimal      not null,
    product_max_dependents   int          not null,
    product_name             varchar(255) not null,
    product_term             int          not null,
    product_price            decimal      not null,
    product_start_message    longtext     not null,
    product_reminder_message longtext     not null,
    product_renewal_message  longtext     not null
);

