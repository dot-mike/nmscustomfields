select `devices`.`device_id`,
    `devices`.`hostname`
from `devices`
where `devices`.`device_id` = 1
    and exists (
        select *
        from `custom_fields`
            inner join `custom_field_device` on `custom_fields`.`id` = `custom_field_device`.`custom_field_id`
        where `devices`.`device_id` = `custom_field_device`.`device_id`
            and `custom_fields`.`id` = 1
    )