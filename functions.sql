CREATE OR REPLACE FUNCTION calculate_workout_end_time()
RETURN TRIGGER AS $$
BEGIN
    -- Если end_time не указан, проводится расчет
    IF NEW.end_time IS NULL THEN
        SELECT NEW.start_time + (duration_min * INTERVAL '1 minute')
        INTO NEW.end_time
        FROM workouts
        WHERE id = NEW.workout_id;

        -- Если время конца не известно, устанавливается +1 час
        IF NEW.end_time IS NULL THEN
            NEW.end_time := NEW.start_time + INTERVAL '1 hour';
        END IF;
    END IF;

    -- Вставка свободных мест, если не указано
    IF NEW.available_slots IS NULL THEN
        SELECT max_participants
        INTO NEW.available_slots
        FROM workouts
        WHERE id = NEW.workout_id;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Триггер для расчета времени
CREATE TRIGGER trg_workout_schedule_before_insert
BEFORE INSERT ON workout_schedule
FOR EACH ROW
EXECUTE FUNCTION calculate_workout_end_time();

-- Триггер на возвращение места
CREATE OR REPLACE FUNCTION update_available_slots_on_delete()
RETURNS TRIGGER AS $$
BEGIN  
    UPDATE workout_schedule
    SET available_slots = available_slots + 1
    WHERE id = OLD.schedule_id;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

-- Функция для определения статуса тренировки
CREATE OR REPLACE FUNCTION update_workout_statuses()
RETURNS void AS $$
BEGIN  
    -- Пометка завершенных тренировок
    UPDATE workout_schedule
    SET status = 'completed'
    WHERE end_time <= NOW()
        AND status NOT IN ('competed', 'cancelled');
    
    -- Пометка тренировок в процессе
    UPDATE workout_schedule
    SET status = 'in_progress'
    WHERE start_time <= NOW()
        end_time > NOW()
        AND status = 'scheduled';
END;
$$ LANGUAGE plpgsql;
