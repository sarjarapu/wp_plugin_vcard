-- Create MySQL event for auto-cleanup of expired reservations
CREATE EVENT IF NOT EXISTS {$prefix}minisite_purge_reservations_event
ON SCHEDULE EVERY 15 MINUTE
DO
  DELETE FROM {$prefix}minisite_reservations 
  WHERE expires_at < NOW();
