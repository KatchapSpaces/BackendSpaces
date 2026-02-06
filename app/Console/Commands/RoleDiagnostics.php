<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RoleDiagnostics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:diagnose {--email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump role counts (accepted + pending) scoped to a super_admin (by email or first super_admin)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');

        if ($email) {
            $user = \App\Models\User::where('email', $email)->first();
        } else {
            $user = \App\Models\User::whereHas('role', function($q){ $q->where('name','super_admin'); })->first();
        }

        if (!$user) {
            $this->error('No super_admin user found');
            return 1;
        }

        $this->info('Super admin: ' . $user->email . ' (id: ' . $user->id . ')');

        $companyIds = \App\Models\Company::where('created_by_user_id', $user->id)->pluck('id')->toArray();
        if (empty($companyIds)) {
            $companyIds = \App\Models\Company::where('email', $user->email)->pluck('id')->toArray();
        }

        $this->info('Company IDs: ' . json_encode($companyIds));

        $companies = \App\Models\Company::whereIn('id', $companyIds)->get(['id','name','email','created_by_user_id']);
        $this->info('Companies: ' . $companies->pluck('name')->toJson());

        // Additional diagnostics: list users in these companies
        $usersInCompanies = collect([]);
        if (!empty($companyIds)) {
            $usersInCompanies = \App\Models\User::whereIn('company_id', $companyIds)
                ->select('id','email','company_id','role_id')
                ->get();
        }

        if ($usersInCompanies->isEmpty()) {
            $this->info('Users in companies: NONE');
        } else {
            $this->info('Users in companies: ' . $usersInCompanies->toJson());
        }

        // Invitations that target these companies (by name) or were created by this super admin
        $companyNames = \App\Models\Company::whereIn('id', $companyIds)->pluck('name')->toArray();
        $invitesQuery = \App\Models\Invitation::whereNull('accepted_at')
            ->where(function($q) use ($companyNames, $user) {
                if (!empty($companyNames)) {
                    $q->orWhereIn('company', $companyNames);
                }
                $q->orWhere('invited_by', $user->id);
            });

        $relatedInvites = $invitesQuery->select('id','email','company','role','frontend_role','invited_by')->get();

        if ($relatedInvites->isEmpty()) {
            $this->info('Relevant pending invitations: NONE');
        } else {
            $this->info('Relevant pending invitations: ' . $relatedInvites->toJson());
        }

        $roles = \App\Models\Role::all();

        foreach ($roles as $role) {
            if (empty($companyIds)) {
                $acceptedUsers = collect([]);
            } else {
                $acceptedUsers = \App\Models\User::where('role_id', $role->id)->whereIn('company_id', $companyIds)->select('id','email','company_id')->get();
            }

            $companyNames = \App\Models\Company::whereIn('id', $companyIds)->pluck('name')->toArray();

            $pendingInvites = \App\Models\Invitation::whereNull('accepted_at')
                ->where(function($q) use ($role) {
                    $q->whereRaw('LOWER(role) = ?', [strtolower($role->name)])
                      ->orWhereRaw('LOWER(frontend_role) = ?', [strtolower($role->name)]);
                })
                ->where(function($q) use ($user, $companyNames) {
                    $q->where('invited_by', $user->id);
                    if (!empty($companyNames)) {
                        $q->orWhereIn('company', $companyNames);
                    }
                })
                ->select('id','email','company')
                ->get();

            $this->line('----');
            $this->line('Role: ' . $role->name);
            $this->line('Accepted count: ' . $acceptedUsers->count());
            $this->line('Accepted emails: ' . $acceptedUsers->pluck('email')->toJson());
            $this->line('Pending count: ' . $pendingInvites->count());
            $this->line('Pending invites: ' . $pendingInvites->toJson());

            // Also show global counts for comparison
            $globalAccepted = \App\Models\User::where('role_id', $role->id)->select('id','email','company_id')->get();
            $globalPending = \App\Models\Invitation::whereNull('accepted_at')
                ->where(function($q) use ($role) {
                    $q->whereRaw('LOWER(role) = ?', [strtolower($role->name)])
                      ->orWhereRaw('LOWER(frontend_role) = ?', [strtolower($role->name)]);
                })->select('id','email','company','invited_by')->get();

            $this->line('Global accepted count: ' . $globalAccepted->count());
            $this->line('Global accepted emails: ' . $globalAccepted->pluck('email')->toJson());

            // Attach company names to global accepted users for clarity
            $globalAcceptedWithCompany = $globalAccepted->map(function($u) {
                $c = \App\Models\Company::find($u->company_id);
                return [
                    'email' => $u->email,
                    'company_id' => $u->company_id,
                    'company' => $c ? $c->name : null
                ];
            });
            $this->line('Global accepted with company: ' . $globalAcceptedWithCompany->toJson());

            // Print company details for global accepted users
            $globalCompanyIds = $globalAccepted->pluck('company_id')->filter()->unique()->toArray();
            if (empty($globalCompanyIds)) {
                $this->line('Global companies with role users: NONE');
            } else {
                $globalCompanies = \App\Models\Company::whereIn('id', $globalCompanyIds)->get(['id','name','email','created_by_user_id']);
                $this->line('Global companies with role users: ' . $globalCompanies->toJson());
            }

            $this->line('Global pending count: ' . $globalPending->count());
            $this->line('Global pending invites: ' . $globalPending->toJson());
        }

        return 0;
    }
}
