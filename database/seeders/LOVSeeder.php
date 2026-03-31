<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LOVSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lovs = [
            // Ticket Statuses
            ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_VALUE' => 'open', 'LOV_DISPLAY_NAME' => 'Open', 'LOV_SEQ_NO' => 1, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_VALUE' => 'on_process', 'LOV_DISPLAY_NAME' => 'On Process', 'LOV_SEQ_NO' => 2, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_VALUE' => 'done', 'LOV_DISPLAY_NAME' => 'Done', 'LOV_SEQ_NO' => 3, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_VALUE' => 'rejected', 'LOV_DISPLAY_NAME' => 'Rejected', 'LOV_SEQ_NO' => 4, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_VALUE' => 'closed', 'LOV_DISPLAY_NAME' => 'Closed', 'LOV_SEQ_NO' => 5, 'IS_ACTIVE' => true],
            
            // Contract Statuses
            ['LOV_TYPE' => 'CONTRACT_STATUS', 'LOV_VALUE' => 'draft', 'LOV_DISPLAY_NAME' => 'Draft', 'LOV_SEQ_NO' => 1, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'CONTRACT_STATUS', 'LOV_VALUE' => 'active', 'LOV_DISPLAY_NAME' => 'Active', 'LOV_SEQ_NO' => 2, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'CONTRACT_STATUS', 'LOV_VALUE' => 'expired', 'LOV_DISPLAY_NAME' => 'Expired', 'LOV_SEQ_NO' => 3, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'CONTRACT_STATUS', 'LOV_VALUE' => 'terminated', 'LOV_DISPLAY_NAME' => 'Terminated', 'LOV_SEQ_NO' => 4, 'IS_ACTIVE' => true],
            ['LOV_TYPE' => 'CONTRACT_STATUS', 'LOV_VALUE' => 'closed', 'LOV_DISPLAY_NAME' => 'Closed', 'LOV_SEQ_NO' => 5, 'IS_ACTIVE' => true],
        ];

        foreach ($lovs as $lov) {
            \App\Models\TicketStatus::withoutGlobalScope('ticket_status')->updateOrCreate(
                ['LOV_TYPE' => $lov['LOV_TYPE'], 'LOV_VALUE' => $lov['LOV_VALUE']],
                $lov
            );
        }
    }
}
