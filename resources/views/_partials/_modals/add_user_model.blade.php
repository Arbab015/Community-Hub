<!-- Edit User Modal -->
<div class="modal fade" id="editUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-6">
          <h4 class="mb-2">Edit User Information</h4>
          <p>Updating user details will receive a privacy audit.</p>
        </div>
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
          <div class="step" data-target="#social-links-validation">
            <button type="button" class="step-trigger">
              <span class="bs-stepper-circle">3</span>
              <span class="bs-stepper-label">
                <span class="bs-stepper-title">Social Links</span>
                <span class="bs-stepper-subtitle">Add social links</span>
              </span>
            </button>
          </div>
        </div>
        <div class="bs-stepper-content">
          <form id="wizard-validation-form" onSubmit="return false">
            <!-- Account Details -->
            <div id="account-details-validation" class="content">
              <div class="content-header mb-4">
                <h6 class="mb-0">Account Details</h6>
                <small>Enter Your Account Details.</small>
              </div>
              <div class="row g-6">
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationUsername">Username</label>
                  <input type="text" name="formValidationUsername" id="formValidationUsername" class="form-control"
                    placeholder="johndoe" />
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationEmail">Email</label>
                  <input type="email" name="formValidationEmail" id="formValidationEmail" class="form-control"
                    placeholder="john.doe@email.com" aria-label="john.doe" />
                </div>
                <div class="col-sm-6 form-control-validation form-password-toggle">
                  <label class="form-label" for="formValidationPass">Password</label>
                  <div class="input-group input-group-merge">
                    <input type="password" id="formValidationPass" name="formValidationPass" class="form-control"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="formValidationPass2" />
                    <span class="input-group-text cursor-pointer" id="formValidationPass2"><i
                        class="icon-base ti tabler-eye-off"></i></span>
                  </div>
                </div>
                <div class="col-sm-6 form-control-validation form-password-toggle">
                  <label class="form-label" for="formValidationConfirmPass">Confirm Password</label>
                  <div class="input-group input-group-merge">
                    <input type="password" id="formValidationConfirmPass" name="formValidationConfirmPass"
                      class="form-control"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="formValidationConfirmPass2" />
                    <span class="input-group-text cursor-pointer" id="formValidationConfirmPass2"><i
                        class="icon-base ti tabler-eye-off"></i></span>
                  </div>
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
                  <label class="form-label" for="formValidationFirstName">First Name</label>
                  <input type="text" id="formValidationFirstName" name="formValidationFirstName"
                    class="form-control" placeholder="John" />
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationLastName">Last Name</label>
                  <input type="text" id="formValidationLastName" name="formValidationLastName" class="form-control"
                    placeholder="Doe" />
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationCountry">Country</label>
                  <select class="form-select select2" id="formValidationCountry" name="formValidationCountry">
                    <option label=" "></option>
                    <option>UK</option>
                    <option>USA</option>
                    <option>Spain</option>
                    <option>France</option>
                    <option>Italy</option>
                    <option>Australia</option>
                  </select>
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationLanguage">Language</label>
                  <select class="selectpicker w-auto" id="formValidationLanguage" data-style="btn-transparent"
                    data-icon-base="icon-base ti" data-tick-icon="tabler-check text-white"
                    name="formValidationLanguage" multiple>
                    <option>English</option>
                    <option>French</option>
                    <option>Spanish</option>
                  </select>
                </div>
                <div class="col-12 d-flex justify-content-between">
                  <button class="btn btn-label-secondary btn-prev">
                    <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2 me-0"></i>
                    <span class="align-middle d-sm-inline-block d-none">Previous</span>
                  </button>
                  <button class="btn btn-primary btn-next"><span
                      class="align-middle d-sm-inline-block d-none me-sm-2">Next</span> <i
                      class="icon-base ti tabler-arrow-right icon-xs"></i></button>
                </div>
              </div>
            </div>
            <!-- Social Links -->
            <div id="social-links-validation" class="content">
              <div class="content-header mb-4">
                <h6 class="mb-0">Social Links</h6>
                <small>Enter Your Social Links.</small>
              </div>
              <div class="row g-6">
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationTwitter">Twitter</label>
                  <input type="text" name="formValidationTwitter" id="formValidationTwitter" class="form-control"
                    placeholder="https://twitter.com/abc" />
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationFacebook">Facebook</label>
                  <input type="text" name="formValidationFacebook" id="formValidationFacebook" class="form-control"
                    placeholder="https://facebook.com/abc" />
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationGoogle">Google+</label>
                  <input type="text" name="formValidationGoogle" id="formValidationGoogle" class="form-control"
                    placeholder="https://plus.google.com/abc" />
                </div>
                <div class="col-sm-6 form-control-validation">
                  <label class="form-label" for="formValidationLinkedIn">LinkedIn</label>
                  <input type="text" name="formValidationLinkedIn" id="formValidationLinkedIn" class="form-control"
                    placeholder="https://linkedin.com/abc" />
                </div>
                <div class="col-12 d-flex justify-content-between">
                  <button class="btn btn-label-secondary btn-prev">
                    <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2 me-0"></i>
                    <span class="align-middle d-sm-inline-block d-none">Previous</span>
                  </button>
                  <button class="btn btn-success btn-next btn-submit">Submit</button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->