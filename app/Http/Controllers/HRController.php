<?php

namespace App\Http\Controllers;

use DB;
use Hash;
use Session;
use Validator;
use App\Models\User;
use App\Models\Leave;
use App\Models\Holiday;
use App\Models\Department;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\LeaveInformation;
use Illuminate\Support\Facades\Log;

class HRController extends Controller
{
    /** Employee list */
    public function employeeList()
    {
        // Retrieve all employees
        $employeeList = User::all();
        
        // Get the latest user ID and generate the next employee ID
        $employeeId = User::generateEmployeeId();

        // Retrieve necessary data for the view
        $roleName = DB::table('role_type_users')->get();
        $position = DB::table('position_types')->get();
        $department = DB::table('departments')->get();
        $statusUser = DB::table('user_types')->get();

        return view('HR.employee', compact('employeeList', 'employeeId', 'roleName', 'position', 'department', 'statusUser'));
    }

    /** save record employee */
    public function employeeSaveRecord(Request $request)
    {
        // ✅ Validate the input
        $validated = $request->validate([
            'photo'        => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255|unique:users,email',
            'position'     => 'required|string|max:255',
            'department'   => 'required|string|max:255',
            'role_name'    => 'required|string|max:255',
            'status'       => 'required|string|max:50',
            'phone_number' => 'required|numeric',
            'location'     => 'required|string|max:255',
            'join_date'    => 'required|string',
            'experience'   => 'required|string',
            'designation'  => 'required|string|max:255',
        ]);

        try {
            // ✅ Handle image upload
            $photoName = time() . '_' . Str::slug($request->name) . '.' . $request->photo->extension();
            $uploadPath = public_path('assets/images/user');

            // Create directory if not exists
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $request->photo->move($uploadPath, $photoName);

            // ✅ Create user
            User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'position'     => $validated['position'],
                'department'   => $validated['department'],
                'role_name'    => $validated['role_name'],
                'status'       => $validated['status'],
                'phone_number' => $validated['phone_number'],
                'location'     => $validated['location'],
                'join_date'    => $validated['join_date'],
                'experience'   => $validated['experience'],
                'designation'  => $validated['designation'],
                'avatar'       => $photoName,
                'password'     => Hash::make('Hello@123'),
            ]);

            flash('Record added successfully 🙂')->success();
            return back();

        } catch (\Exception $e) {
            Log::error('Employee save failed: ' . $e->getMessage());
            flash('Failed to add record 😞')->error();
            return back()->withInput();
        }
    }

    /** Update Record Employee */
    public function employeeUpdateRecord(Request $request)
    {
        try {
            $user = User::findOrFail($request->id);

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $photoName = time() . '_' . Str::slug($request->name) . '.' . $request->photo->extension();
                $request->photo->move(public_path('assets/images/user'), $photoName);

                // Delete old photo if exists
                if (!empty($user->avatar) && file_exists(public_path('assets/images/user/' . $user->avatar))) {
                    unlink(public_path('assets/images/user/' . $user->avatar));
                }

                $user->avatar = $photoName;
            }

            // Update other fields
            $user->update([
                'name'         => $request->name,
                'email'        => $request->email,
                'position'     => $request->position,
                'department'   => $request->department,
                'role_name'    => $request->role_name,
                'status'       => $request->status,
                'phone_number' => $request->phone_number,
                'location'     => $request->location,
                'join_date'    => $request->join_date,
                'experience'   => $request->experience,
                'designation'  => $request->designation,
            ]);

            flash()->success('Record updated successfully 🙂');
            return back();
        } catch (\Exception $e) {
            \Log::error('Employee update failed: ' . $e->getMessage());
            flash()->error('Failed to update record 😞');
            return back()->withInput();
        }
    }

    /** Delete Record Employee */
    public function employeeDeleteRecord(Request $request)
    {
        try {
            $user = User::findOrFail($request->id_delete);

            // Delete avatar image if exists
            if (!empty($user->avatar)) {
                $avatarPath = public_path('assets/images/user/' . $user->avatar);
                if (file_exists($avatarPath)) {
                    unlink($avatarPath);
                }
            }

            // Delete the user record
            $user->delete();

            return redirect()->back()->with('success', 'Record deleted successfully 🙂');
        } catch (\Exception $e) {
            Log::error('Delete Record Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete record 🙁');
        }
    }

    /** holiday Page */
    public function holidayPage()
    {
        $holidayList = Holiday::all();
        return view('HR.holidays',compact('holidayList'));
    }

    /** save record holiday */
    public function holidaySaveRecord(Request $request)
    {
        $request->validate([
            'holiday_type' => 'required|string',
            'holiday_name' => 'required|string',
            'holiday_date' => 'required|string',
        ]);
    
        try {
            // Use updateOrCreate to handle both creation and update
            $holiday = Holiday::updateOrCreate(
                ['id' => $request->idUpdate],
                [
                    'holiday_type' => $request->holiday_type,
                    'holiday_name' => $request->holiday_name,
                    'holiday_date' => $request->holiday_date,
                ]
            );
    
            flash()->success('Holiday created or updated successfully :)');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error($e); // Log the error
            flash()->error('Failed to add holiday :)');
            return redirect()->back();
        }
    }

    /** delete record */
    public function holidayDeleteRecord(Request $request) 
    {
        try {
            // Find the holiday record or fail if not found
            $holiday = Holiday::findOrFail($request->id_delete);
            $holiday->delete();

            flash()->success('Holiday deleted successfully :)');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error($e); // Log the error
            flash()->error('Failed to delete holiday :)');
            return redirect()->back();
        }
    }

    /** get information leave */
    public function getInformationLeave(Request $request)
    {
        try {

            $numberOfDay = $request->number_of_day;
            $leaveType   = $request->leave_type;
            
            $leaveDay = LeaveInformation::where('leave_type', $leaveType)->first();
            
            if ($leaveDay) {
                $days = $leaveDay->leave_days - ($numberOfDay ?? 0);
            } else {
                $days = 0; // Handle case if leave type doesn't exist
            }
            
            $data = [
                'response_code' => 200,
                'status'        => 'success',
                'message'       => 'Get success',
                'leave_type'    => $days,
                'number_of_day' => $numberOfDay,
            ];
            
            return response()->json($data);

        } catch (\Exception $e) {
            // Log the exception and return an appropriate response
            \Log::error($e->getMessage());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }

    /** leave Employee */
    public function leaveEmployee()
    {
        $annualLeave = LeaveInformation::where('leave_type','Annual Leave')->select('leave_days')->first();
       
        $leave = Leave::where('staff_id', Session::get('user_id'))->get();
        // $leaves = Leave::where('staff_id', Session::get('user_id'))->whereIn('leave_type')->get();
        return view('HR.LeavesManage.leave-employee',compact('leave'));
    }

    /** create Leave Employee */
    public function createLeaveEmployee()
    {
        $leaveInformation = LeaveInformation::all();
        return view('HR.LeavesManage.create-leave-employee',compact('leaveInformation'));
    }

    /** save record leave */
    public function saveRecordLeave(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|string',
            'date_from'  => 'required',
            'date_to'    => 'required',
            'reason'     => 'required',
        ]);

        try {
           
            $save  = new Leave;
            $save->staff_id         = Session::get('user_id');
            $save->employee_name    = Session::get('name');
            $save->leave_type       = $request->leave_type;
            $save->remaining_leave  = $request->remaining_leave;
            $save->date_from        = $request->date_from;
            $save->date_to          = $request->date_to;
            $save->number_of_day    = $request->number_of_day;
            $save->leave_date       = json_encode($request->leave_date);
            $save->leave_day        = json_encode($request->select_leave_day);
            $save->status           = 'Pending';
            $save->reason           = $request->reason;
            $save->save();
    
            flash()->success('Apply Leave successfully :)');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error($e); // Log the error
            flash()->error('Failed Apply Leave :)');
            return redirect()->back();
        }
    }

    /** view detail leave employee */
    public function viewDetailLeave($staff_id)
    {
        $leaveInformation = LeaveInformation::all();
        $leaveDetail = Leave::where('staff_id', $staff_id)->first();
        $leaveDate   = json_decode($leaveDetail->leave_date, true); // Decode JSON to array
        $leaveDay    = json_decode($leaveDetail->leave_day, true); // Decode JSON to array

        return view('HR.LeavesManage.view-detail-leave',compact('leaveInformation','leaveDetail','leaveDate','leaveDay'));
    }

    /** leave HR */
    public function leaveHR()
    {
        return view('HR.LeavesManage.leave-hr');
    }

    /** attendance */
    public function attendance()
    {
        return view('HR.Attendance.attendance');
    }

    /** create Leave HR */
    public function createLeaveHR()
    {
        $users = User::all();
        $leaveInformation = LeaveInformation::all();
        return view('HR.LeavesManage.create-leave-hr',compact('users','leaveInformation'));
    }

    /** attendance Main */
    public function attendanceMain()
    {
        return view('HR.Attendance.attendance-main');
    }

    /** department */
    public function department()
    {
        $departmentList = Department::all();
        return view('HR.department',compact('departmentList'));
    }

    /** save record department */
    public function saveRecordDepartment(Request $request)
    {
        $request->validate([
            'department'      => 'required|string',
            'head_of'         => 'required|string',
            'phone_number'    => 'required|integer',
            'email'           => 'required|email',
            'total_employee'  => 'required|integer',
        ]);
    
        try {
            // Use updateOrCreate to handle both creation and update
            $department = Department::updateOrCreate(
                ['id' => $request->id_update],
                [
                    'department'     => $request->department,
                    'head_of'        => $request->head_of,
                    'phone_number'   => $request->phone_number,
                    'email'          => $request->email,
                    'total_employee' => $request->total_employee,
                ]
            );
    
            flash()->success('Department created or updated successfully :)');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error($e);
            flash()->error('Failed to add or update department :)');
            return redirect()->back();
        }
    }

    /** delete record department */
    public function deleteRecordDepartment(Request $request)
    {
        try {
            // Find the department or fail if not found
            $department = Department::findOrFail($request->id_delete);
            $department->delete();
            
            flash()->success('Record deleted successfully :)');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error($e); // Log the error
            flash()->error('Failed to delete record :)');
            return redirect()->back();
        }
    }

}
