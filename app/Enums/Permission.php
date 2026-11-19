<?php

namespace App\Enums;

enum Permission: string
{
    case ManageUsers = 'manage users';
    case ViewAllReports = 'view all reports';
    case ManageCategories = 'manage categories';
    case ManageSharedProjects = 'manage shared projects';
    case ManageSchoolYears = 'manage school years';
}
