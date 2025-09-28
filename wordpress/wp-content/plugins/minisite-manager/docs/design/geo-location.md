# Geo-location

## MySQL POINT type

```sql
-- Create table with spatial index
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coordinate POINT NOT NULL,
    SPATIAL INDEX(coordinate)
);

-- Insert geolocation points (longitude, latitude)
INSERT INTO locations (coordinate) VALUES (POINT(120.1234, 45.6789));
INSERT INTO locations (coordinate) VALUES (POINT(-120.1234, -45.6789));

-- Find points within a bounding box for efficient index lookup (geo window search)
SELECT
    id,
    ST_X(coordinate) AS longitude,
    ST_Y(coordinate) AS latitude
FROM locations
WHERE MBRContains(
        ST_GeomFromText('POLYGON((110 40, 110 50, 130 50, 130 40, 110 40))'),
        coordinate
      );

-- Find points within a radius from a given location (uses index on POINT)
SELECT
    id,
    ST_X(coordinate) AS longitude,
    ST_Y(coordinate) AS latitude
FROM locations
WHERE ST_Distance_Sphere(coordinate, POINT(120.1, 45.7)) < 50000; -- 50km radius

-- Drop the table when finished
DROP TABLE locations;

```