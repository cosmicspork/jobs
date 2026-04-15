<?php

namespace App;

enum FilterReason: string
{
    case NotRemote = 'not_remote';
    case BelowSalaryMin = 'below_salary_min';
}
