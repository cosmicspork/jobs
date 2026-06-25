<?php

namespace App;

enum FilterReason: string
{
    case NotRemote = 'not_remote';
    case BelowSalaryMin = 'below_salary_min';
    case LocationBlocked = 'location_blocked';
    case MissingMustHave = 'missing_must_have';
    case HitAvoidKeyword = 'hit_avoid_keyword';
}
