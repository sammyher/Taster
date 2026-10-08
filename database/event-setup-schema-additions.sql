-- Columns required by the drafted event-setup PHP pages.
-- Incorporate these definitions into schema.sql rather than repeatedly
-- running this file against production.

USE taster_db;

ALTER TABLE Events
    ADD COLUMN JoinCode VARCHAR(12) NULL,
    ADD CONSTRAINT uq_events_join_code UNIQUE (JoinCode);

ALTER TABLE BeerContainer
    ADD COLUMN Name VARCHAR(100) NOT NULL,
    ADD COLUMN Type VARCHAR(50) NOT NULL;

-- Recommended duplicate protection for the two supported association forms.
CREATE UNIQUE INDEX uq_container_beer
    ON ContainerBeers (ContainerID, BeerID);

CREATE UNIQUE INDEX uq_event_beer
    ON ContainerBeers (EventID, BeerID);

