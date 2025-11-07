# Location Point (POINT Geometry) - Lessons Learned

## Critical Issue: Longitude/Latitude Swapping

### Problem
When working with MySQL POINT geometry type for `location_point` column, there was a critical bug where longitude and latitude values kept swapping, causing incorrect geographic coordinates. This issue took several days to debug and resolve.

### Root Cause
MySQL POINT geometry uses the format `POINT(longitude, latitude)` - **longitude FIRST, then latitude**. This is counterintuitive because:
- Most mapping APIs use `(latitude, longitude)` order
- Geographic conventions often list latitude first
- The order is easy to confuse

### Correct Implementation Pattern

#### Saving POINT to Database
```php
// CORRECT: POINT(lng, lat) - longitude FIRST, latitude SECOND
$sql = "UPDATE table SET location_point = POINT(%f, %f) WHERE id = %d";
$wpdb->query($wpdb->prepare($sql,
    $geo->getLng(),  // FIRST parameter = longitude
    $geo->getLat(),  // SECOND parameter = latitude
    $id
));
```

#### Loading POINT from Database
```php
// CORRECT: ST_X() returns longitude, ST_Y() returns latitude
$pointResult = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT ST_X(location_point) as lng, ST_Y(location_point) as lat
         FROM table WHERE id = %d",
        $id
    ),
    ARRAY_A
);

// Create GeoPoint with correct order
$geo = new GeoPoint(
    lat: (float) $pointResult['lat'],  // ST_Y() = latitude
    lng: (float) $pointResult['lng']  // ST_X() = longitude
);
```

### Key Rules to Follow

1. **POINT Constructor**: Always use `POINT(lng, lat)` - longitude first, latitude second
2. **ST_X()**: Returns longitude (X-axis = east/west)
3. **ST_Y()**: Returns latitude (Y-axis = north/south)
4. **GeoPoint Constructor**: Uses `GeoPoint(lat: ..., lng: ...)` - latitude first (standard convention)

### Current Working Implementation

**File**: `src/Infrastructure/Persistence/Repositories/VersionRepository.php`

**Save (lines 87-90)**:
```php
"UPDATE {$this->table()} SET location_point = POINT(%f, %f) WHERE id = %d",
$version->geo->getLng(),  // FIRST = longitude
$version->geo->getLat(),  // SECOND = latitude
$version->id
```

**Load (lines 311-321)**:
```php
"SELECT ST_X(location_point) as lng, ST_Y(location_point) as lat
 FROM {$this->table()} WHERE id = %d"

// Then create GeoPoint
$geo = new GeoPoint(
    lat: (float) $pointResult['lat'],  // ST_Y() = latitude
    lng: (float) $pointResult['lng']  // ST_X() = longitude
);
```

### DO NOT MODIFY

⚠️ **CRITICAL**: When migrating VersionRepository to Doctrine, **DO NOT CHANGE** the location_point handling logic. The current implementation is correct and working. Any modification risks reintroducing the longitude/latitude swapping bug.

### Migration Guidelines

When converting to Doctrine:
1. **Keep the exact same SQL pattern** for POINT operations
2. **Use raw SQL** for POINT save/load (Doctrine doesn't handle POINT well)
3. **Copy the exact logic** from current `VersionRepository::save()` and `mapRow()`
4. **Test thoroughly** with known coordinates to verify no swapping occurs
5. **Add comments** referencing this document

### Testing Checklist

Before considering location_point migration complete:
- [ ] Save a version with known coordinates (e.g., lat: 32.7767, lng: -96.7970)
- [ ] Load the version and verify coordinates match exactly
- [ ] Test with coordinates in all quadrants (positive/negative lat/lng)
- [ ] Verify on map that location is correct (not swapped)
- [ ] Test edge cases (null geo, zero coordinates)

### Related Files

- `src/Infrastructure/Persistence/Repositories/VersionRepository.php` (current working implementation)
- `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php` (similar pattern)
- `src/Domain/ValueObjects/GeoPoint.php` (value object with lat/lng)

### References

- MySQL POINT documentation: https://dev.mysql.com/doc/refman/8.0/en/spatial-types.html
- MySQL Spatial Functions: https://dev.mysql.com/doc/refman/8.0/en/spatial-function-reference.html

---

**Last Updated**: 2025-11-05
**Status**: Critical - Do not modify without careful testing

