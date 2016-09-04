CREATE TYPE language AS ENUM('en', 'it', 'fr', 'de', 'ru', 'fa', 'hi', 'pt');

CREATE TABLE "User" (
    "chat_id" int,
    "language" language DEFAULT 'en',

    PRIMARY KEY ("user_id")
);

CREATE TABLE "Giveaway" (
    "id" SERIAL,
    "name" VARCHAR(32),
    "hashtag" VARCHAR(32),
    "desc" VARCHAR(50),
    "max_partecipants" int DEFAULT 0, /* 0 for no limit */
    "owner_id" int,
    "created" date,
    "end" date,

    PRIMARY KEY ("id"),
    FOREIGN KEY ("owner_id") REFERENCES "User" ("user_id"),
    CONSTRAINT hashtag_unique UNIQUE "hashtag"
);

CREATE TABLE "Type" (
    "id" SERIAL,
    "name" VARCHAR(32),

    PRIMARY KEY ("id")
);

CREATE TABLE "Prize" (
    "id" SERIAL,
    "name" VARCHAR(32),
    "value" float,
    "currency" VARCHAR(1) DEFAULT '€',
    "giveaway" int,
    "type" int,

    PRIMARY KEY ("id"),
    FOREIGN KEY ("giveaway") REFERENCES "Giveaway" ("id"),
    FOREIGN KEY ("type") REFERENCES "Type" ("id")
);

CREATE TABLE "Joined" (
    "user_id" int,
    "giveaway_id" int,
    "references" smallint DEFAULT 0,

    PRIMARY KEY ("user_id", "giveaway_id"),
    FOREIGN KEY ("user_id") REFERENCES "User" ("user_id"),
    FOREIGN KEY ("giveaway_id") REFERENCES "Giveaway" ("id")
);

CREATE TABLE "Won" (
    "user_id" int,
    "giveaway_id" int,
    "id_prize" int,

    PRIMARY KEY ("user_id", "giveaway_id", "id_prize"),
    FOREIGN KEY "user_id" REFERENCES "User" ("user_id"),
    FOREIGN KEY "giveaway_id" REFERENCES "Giveaway" ("id"),
    FOREIGN KEY "id_prize" REFERENCES "Prize" ("id")
);

INSERT INTO "Type" ("name") VALUES ('Videogames');
INSERT INTO "Type" ("name") VALUES ('Coupon');
INSERT INTO "Type" ("name") VALUES ('Gift card');
INSERT INTO "Type" ("name") VALUES ('Other');
