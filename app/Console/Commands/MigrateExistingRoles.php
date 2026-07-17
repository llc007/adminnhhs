<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MigrateExistingRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-existing-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing JSON pivot roles to Spatie permission tables with school scoping';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting migration of school roles...');

        $pivotRecords = DB::table('school_user')->whereNotNull('roles')->get();

        if ($pivotRecords->isEmpty()) {
            $this->warn('No role records found in school_user table.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($pivotRecords));
        $bar->start();

        foreach ($pivotRecords as $record) {
            $user = User::find($record->user_id);
            if (! $user) {
                $bar->advance();

                continue;
            }

            $roles = json_decode($record->roles, true);
            if (! is_array($roles) || empty($roles)) {
                $bar->advance();

                continue;
            }

            foreach ($roles as $roleName) {
                if (empty($roleName)) {
                    continue;
                }

                // Find or create the Spatie role for this school (team_id)
                $role = Role::where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->where('team_id', $record->school_id)
                    ->first();

                if (! $role) {
                    $role = Role::create([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'team_id' => $record->school_id,
                    ]);
                }

                // Associate user with the role scoped to this school team_id
                $exists = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_id', $user->id)
                    ->where('model_type', User::class)
                    ->where('team_id', $record->school_id)
                    ->exists();

                if (! $exists) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $role->id,
                        'model_id' => $user->id,
                        'model_type' => User::class,
                        'team_id' => $record->school_id,
                    ]);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Migration completed successfully!');

        return Command::SUCCESS;
    }
}
