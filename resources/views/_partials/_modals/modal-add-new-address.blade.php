<!-- Add New Address Modal -->
<div class="modal fade" id="add_new_user" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-6">
          <h4 class="address-title mb-2">Add New User</h4>
        </div>
        <div class="col-12 mb-6">
          <div id="wizard-validation" class="bs-stepper mt-2">
            <div class="bs-stepper-header">
              <div class="step" data-target="#account-details-validation">
                <button type="button" class="step-trigger">
                  <span class="bs-stepper-circle">1</span>
                  <span class="bs-stepper-label mt-1">
                    <span class="bs-stepper-title">Account Details</span>
                    <span class="bs-stepper-subtitle">Setup Account Details</span>
                  </span>
                </button>
              </div>
              <div class="line">
                <i class="icon-base ti tabler-chevron-right"></i>
              </div>
              <div class="step" data-target="#personal-info-validation">
                <button type="button" class="step-trigger">
                  <span class="bs-stepper-circle">2</span>
                  <span class="bs-stepper-label">
                    <span class="bs-stepper-title">Personal Info</span>
                    <span class="bs-stepper-subtitle">Add personal info</span>
                  </span>
                </button>
              </div>
              <div class="line">
                <i class="icon-base ti tabler-chevron-right"></i>
              </div>
            </div>
            <div class="bs-stepper-content">
              <form id="wizard-validation-form" method="post" action="{{ route('user.create') }}">
                @csrf
                <!-- Account Details -->
                <div id="account-details-validation" class="content">
                  <div class="content-header mb-4">
                    <h6 class="mb-0">Account Details</h6>
                    <small>Enter Your Account Details.</small>
                  </div>
                  <div class="row g-6">
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="name">Username</label>
                      <input type="text" name="name" id="name" class="form-control" placeholder="johndoe" />
                    </div>
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="email">Email</label>
                      <input type="email" name="email" id="email" class="form-control"
                        placeholder="john.doe@email.com" aria-label="john.doe" />
                    </div>
                    <div class="col-sm-6 form-control-validation form-password-toggle">
                      <label class="form-label" for="password">Password</label>
                      <div class="input-group input-group-merge">
                        <input type="password" id="password" name="password" class="form-control"
                          placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                          aria-describedby="password2" />
                        <span class="input-group-text cursor-pointer" id="password2"><i
                            class="icon-base ti tabler-eye-off"></i></span>
                      </div>
                    </div>
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="role">Role</label>
                      <input type="text" id="role" name="role" class="form-control" placeholder="admin" />
                    </div>

                    <div class="col-12 d-flex justify-content-between">
                      <button class="btn btn-label-secondary btn-prev" disabled>
                        <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2 me-0"></i>
                        <span class="align-middle d-sm-inline-block d-none">Previous</span>
                      </button>
                      <button class="btn btn-primary btn-next"><span
                          class="align-middle d-sm-inline-block d-none me-sm-2">Next</span> <i
                          class="icon-base ti tabler-arrow-right icon-xs"></i></button>
                    </div>
                  </div>
                </div>
                <!-- Personal Info -->
                <div id="personal-info-validation" class="content">
                  <div class="content-header mb-4">
                    <h6 class="mb-0">Personal Info</h6>
                    <small>Enter Your Personal Info.</small>
                  </div>
                  <div class="row g-6">
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="cnic_passport">CNIC/Possport</label>
                      <input type="text" id="cnic_passport" name="cnic_passport" class="form-control"
                        placeholder="13503-0233994-3" />
                    </div>
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="dob">DOB</label>
                      <input type="date" id="dob" name="dob" class="form-control"
                        placeholder="18-11-1998" />
                    </div>
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="country">Country</label>
                      <select class="form-select select2" id="country" name="country">
                        <option label=" "></option>
                        <option>Pak</option>
                        <option>UK</option>
                        <option>USA</option>
                        <option>China</option>
                        <option>France</option>
                        <option>Italy</option>
                        <option>Australia</option>
                        <option>Other</option>
                      </select>
                    </div>

                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="profession">Profession</label>
                      <input type="text" id="profession" name="profession" class="form-control"
                        placeholder="Bank Manager" />
                    </div>

                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="contact">Contact</label>
                      <input type="text" id="contact" name="contact" class="form-control"
                        placeholder="03148773882" />
                    </div>
                    <div class="col-sm-6 form-control-validation">
                      <label class="form-label" for="emergency_contact">Emergency Content</label>
                      <input type="text" id="emergency_contact" name="emergency_contact" class="form-control"
                        placeholder="03148773882" />
                    </div>

                    {{-- gender --}}
                    <div class="col-sm-6 form-control-validation">
                      <label class="d-block form-label">Gender</label>
                      <div class="d-flex">
                        <div class="form-check mb-2">
                          <input type="radio" id="male" name="gender" class="form-check-input pe-1" required
                            checked />
                          <label class="form-check-label pe-2" for="male">Male</label>
                        </div>
                        <div class="form-check">
                          <input type="radio" id="female" name="gender" class="form-check-input pe-1"
                            required />
                          <label class="form-check-label pe-2" for="female">Female</label>
                        </div>
                        <div class="form-check">
                          <input type="radio" id="other" name="gender" class="form-check-input pe-1"
                            required />
                          <label class="form-check-label pe-2" for="other">Other</label>
                        </div>
                      </div>
                    </div>

                    {{-- married-status --}}
                    <div class="col-sm-6 form-control-validation">
                      <label class="d-block form-label">Married_Status</label>
                      <div class="d-flex">
                        <div class="form-check mb-2">
                          <input type="radio" id="married" name="married_status" class="form-check-input p"
                            required checked />
                          <label class="form-check-label pe-2" for="married">Married</label>
                        </div>
                        <div class="form-check">
                          <input type="radio" id="unmarried" name="married_status" class="form-check-input"
                            required />
                          <label class="form-check-label pe-2" for="unmarried">Un-married</label>
                        </div>
                      </div>
                    </div>


                    <div class="col-sm-12 form-control-validation">
                      <label class="form-label" for="present_address">Present Address</label>
                      <input type="textarea" id="present_address" name="present_address" class="form-control"
                        placeholder="house no 34, street 4, ghori town, lahore" />
                    </div>
                    <div class="col-sm-12 form-control-validation">
                      <label class="form-label" for="permanent_address">Permanent Address</label>
                      <input type="textarea" id="permanent_address" name="permanent_address" class="form-control"
                        placeholder="house no 34, street 4, ghori town, lahore" />
                    </div>

                    <div class="col-12 d-flex justify-content-between">
                      <button class="btn btn-label-secondary btn-prev">
                        <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2 me-0"></i>
                        <span class="align-middle d-sm-inline-block d-none">Previous</span>
                      </button>
                      <button type="submit" class="btn btn-success btn-submit">
                        Submit
                      </button>
                    </div>
                  </div>
                </div>

            </div>
          </div>
          </form>
        </div>
      </div>
    </div>
    <!-- /Validation Wizard -->

  </div>
</div>
</div>
<!--/ Add New Address Modal -->
