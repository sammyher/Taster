-- Taster local-development seed data
--
-- Purpose:
--   1. Provide the account used by dev-login.php.
--   2. Provide a compact, varied catalog of real beers for event-setup testing.
--
-- Run schema.sql before this file. This file intentionally creates no events,
-- participants, containers, reviews, or favorites.

USE taster_db;

START TRANSACTION;

-- Simulated Auth0 user for local development.
INSERT IGNORE INTO Accounts (ID, DisplayName, Email)
VALUES ('dev-account-001', 'Development Host', 'dev-host@example.com');

-- Catalog beers are not owned by the development account, so AccountID is NULL.
-- IBU is NULL where a dependable brewery-published value is not consistently
-- available. Product specifications can change; these rows are test fixtures,
-- not an authoritative beer reference.
INSERT IGNORE INTO BeerCatalog
    (ID, Name, Brewery, Type, ABV, IBU, Description, AccountID)
VALUES
    ('seed-beer-001', 'Guinness Draught', 'Guinness', 'Irish Dry Stout', 4.20, NULL,
     'Nitrogenated Irish stout with roasted malt character and a creamy finish.', NULL),
    ('seed-beer-002', 'Sierra Nevada Pale Ale', 'Sierra Nevada Brewing Co.', 'American Pale Ale', 5.60, 38.00,
     'Cascade-forward pale ale with citrus, pine, and caramel-malt character.', NULL),
    ('seed-beer-003', 'Hazy Little Thing', 'Sierra Nevada Brewing Co.', 'Hazy IPA', 6.70, 35.00,
     'Unfiltered IPA with fruit-forward hops, a smooth body, and modest bitterness.', NULL),
    ('seed-beer-004', 'Stone IPA', 'Stone Brewing', 'American IPA', 6.90, NULL,
     'West Coast IPA featuring citrusy and piney hop flavors with a dry finish.', NULL),
    ('seed-beer-005', 'Sculpin IPA', 'Ballast Point Brewing Company', 'American IPA', 7.00, 70.00,
     'Hop-forward IPA known for bright citrus and tropical-fruit notes.', NULL),
    ('seed-beer-006', 'Lagunitas IPA', 'Lagunitas Brewing Company', 'American IPA', 6.20, 51.50,
     'American IPA balancing resinous hops with caramel malt.', NULL),
    ('seed-beer-007', 'Two Hearted Ale', 'Bell''s Brewery', 'American IPA', 7.00, 55.00,
     'Centennial-hopped IPA with grapefruit, pine, and a firm malt backbone.', NULL),
    ('seed-beer-008', '60 Minute IPA', 'Dogfish Head Craft Brewery', 'American IPA', 6.00, 60.00,
     'Continuously hopped IPA with citrusy hops and balanced bitterness.', NULL),
    ('seed-beer-009', 'All Day IPA', 'Founders Brewing Co.', 'Session IPA', 4.70, 42.00,
     'Lower-alcohol IPA with aromatic hops and a crisp finish.', NULL),
    ('seed-beer-010', 'Pliny the Elder', 'Russian River Brewing Company', 'Double IPA', 8.00, 100.00,
     'Double IPA with intense pine, citrus, and floral hop character.', NULL),
    ('seed-beer-011', 'Fat Tire Ale', 'New Belgium Brewing', 'Golden Ale', 5.20, 15.00,
     'Easy-drinking golden ale with light malt and subtle hop character.', NULL),
    ('seed-beer-012', 'Samuel Adams Boston Lager', 'Boston Beer Company', 'Vienna Lager', 5.00, 30.00,
     'Amber lager combining toasted malt with a clean hop finish.', NULL),
    ('seed-beer-013', 'Brooklyn Lager', 'Brooklyn Brewery', 'Amber Lager', 5.20, 33.00,
     'Dry-hopped amber lager with toasted malt and floral hop notes.', NULL),
    ('seed-beer-014', '805', 'Firestone Walker Brewing Company', 'Blonde Ale', 4.70, 15.00,
     'Light-bodied blonde ale with restrained malt sweetness and a clean finish.', NULL),
    ('seed-beer-015', 'Kona Big Wave', 'Kona Brewing Company', 'Golden Ale', 4.40, 21.00,
     'Light golden ale with gentle tropical hop character.', NULL),
    ('seed-beer-016', 'Blue Moon Belgian White', 'Blue Moon Brewing Company', 'Belgian-Style Witbier', 5.40, 9.00,
     'Wheat ale brewed with citrus peel and coriander-like spice notes.', NULL),
    ('seed-beer-017', 'Allagash White', 'Allagash Brewing Company', 'Belgian-Style Witbier', 5.20, NULL,
     'Cloudy wheat beer with citrus, coriander, and soft yeast character.', NULL),
    ('seed-beer-018', 'Hoegaarden Original White Ale', 'Hoegaarden', 'Belgian Witbier', 4.90, 15.00,
     'Belgian wheat beer with orange-citrus, spice, and a soft finish.', NULL),
    ('seed-beer-019', 'Weihenstephaner Hefeweissbier', 'Bayerische Staatsbrauerei Weihenstephan', 'Hefeweizen', 5.40, 14.00,
     'Bavarian wheat beer with banana, clove, and yeast-driven aroma.', NULL),
    ('seed-beer-020', 'Pilsner Urquell', 'Plzensky Prazdroj', 'Czech Pilsner', 4.40, 40.00,
     'Czech pale lager with bready malt and assertive Saaz hop bitterness.', NULL),
    ('seed-beer-021', 'Modelo Especial', 'Grupo Modelo', 'Mexican Pale Lager', 4.40, NULL,
     'Crisp pale lager with light grain character and mild hop bitterness.', NULL),
    ('seed-beer-022', 'Pacifico Clara', 'Grupo Modelo', 'Mexican Pale Lager', 4.40, NULL,
     'Light, crisp lager with gentle malt flavor and a dry finish.', NULL),
    ('seed-beer-023', 'Corona Extra', 'Grupo Modelo', 'Mexican Pale Lager', 4.60, NULL,
     'Light-bodied pale lager with restrained malt and hop flavors.', NULL),
    ('seed-beer-024', 'Heineken Original', 'Heineken', 'European Pale Lager', 5.00, NULL,
     'Pale lager with grainy malt, herbal hops, and a crisp finish.', NULL),
    ('seed-beer-025', 'Stella Artois', 'Stella Artois', 'European Pale Lager', 5.00, NULL,
     'European pale lager with light malt sweetness and herbal hop character.', NULL),
    ('seed-beer-026', 'Budweiser', 'Anheuser-Busch', 'American Lager', 5.00, 12.00,
     'Light-bodied American lager with mild grain flavor and a clean finish.', NULL),
    ('seed-beer-027', 'Miller Lite', 'Miller Brewing Company', 'American Light Lager', 4.20, 10.00,
     'Low-calorie light lager with restrained malt and hop character.', NULL),
    ('seed-beer-028', 'Coors Banquet', 'Coors Brewing Company', 'American Lager', 5.00, NULL,
     'American lager with mild malt sweetness and a crisp finish.', NULL),
    ('seed-beer-029', 'Duvel', 'Duvel Moortgat', 'Belgian Strong Golden Ale', 8.50, 33.00,
     'Highly carbonated Belgian golden ale with fruity yeast and a dry finish.', NULL),
    ('seed-beer-030', 'Tripel Karmeliet', 'Brouwerij Bosteels', 'Belgian Tripel', 8.40, NULL,
     'Belgian tripel with fruit, spice, grain, and a warming dry finish.', NULL),
    ('seed-beer-031', 'Westmalle Tripel', 'Brouwerij der Trappisten van Westmalle', 'Belgian Tripel', 9.50, 36.00,
     'Trappist tripel with fruity fermentation notes, spice, and firm carbonation.', NULL),
    ('seed-beer-032', 'Chimay Blue', 'Bieres de Chimay', 'Belgian Strong Dark Ale', 9.00, 35.00,
     'Dark Trappist ale with caramel, dark fruit, spice, and warming alcohol.', NULL),
    ('seed-beer-033', 'Orval', 'Brasserie d''Orval', 'Belgian Pale Ale', 6.20, 36.00,
     'Dry Trappist ale with herbal hops, fruit, and distinctive Brett character.', NULL),
    ('seed-beer-034', 'La Fin du Monde', 'Unibroue', 'Belgian-Style Tripel', 9.00, 19.00,
     'Strong golden ale with citrus, spice, fruit, and expressive yeast character.', NULL),
    ('seed-beer-035', 'Rodenbach Grand Cru', 'Brouwerij Rodenbach', 'Flanders Red Ale', 6.00, NULL,
     'Oak-aged sour red ale with dark fruit, acidity, and balsamic complexity.', NULL),
    ('seed-beer-036', 'Lindemans Framboise', 'Lindemans Brewery', 'Fruit Lambic', 2.50, 12.00,
     'Raspberry lambic with prominent fruit sweetness and balancing tartness.', NULL),
    ('seed-beer-037', 'Black Butte Porter', 'Deschutes Brewery', 'American Porter', 5.50, 30.00,
     'Dark porter with chocolate, coffee, and roasted-malt flavors.', NULL),
    ('seed-beer-038', 'Milk Stout Nitro', 'Left Hand Brewing Company', 'Milk Stout', 6.00, 25.00,
     'Nitrogenated sweet stout with chocolate, coffee, and a creamy body.', NULL),
    ('seed-beer-039', 'Celebrator Doppelbock', 'Ayinger Privatbrauerei', 'Doppelbock', 6.70, 24.00,
     'Dark German lager with rich bread crust, caramel, and dried-fruit notes.', NULL),
    ('seed-beer-040', 'Paulaner Oktoberfest Marzen', 'Paulaner Brauerei', 'Marzen', 5.80, 20.00,
     'Amber festival lager with toasted malt, gentle sweetness, and a clean finish.', NULL);

COMMIT;

-- Quick verification:
-- SELECT ID, DisplayName, Email FROM Accounts WHERE ID = 'dev-account-001';
-- SELECT COUNT(*) AS SeedBeerCount FROM BeerCatalog WHERE ID LIKE 'seed-beer-%';
