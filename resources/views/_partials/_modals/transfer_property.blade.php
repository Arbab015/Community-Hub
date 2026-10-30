@php
  $transferState = app(\App\Services\Property\PropertyTransferService::class)->eligibility($ownerable);
  $transferQuote = $transferState['quote'];
  $transferCurrency = $transferQuote['currency'];
  $transferFeeLabel = $transferQuote['matched']
      ? ($transferQuote['is_percentage']
          ? rtrim(rtrim(number_format($transferQuote['percent'], 2, '.', ''), '0'), '.').'% of listed price = '
            .number_format($transferQuote['amount'], 2).' '.$transferCurrency
          : number_format($transferQuote['amount'], 2).' '.$transferCurrency)
      : 'No transfer fee configured for this society — 0.00 '.$transferCurrency;
@endphp

<div class="modal fade" id="transferPropertyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form action="{{ route('property_transfers.store') }}" method="POST" enctype="multipart/form-data" id="transferPropertyForm">
        @csrf
        <input type="hidden" name="ownerable_type" value="{{ $ownerableType }}">
        <input type="hidden" name="ownerable_id" value="{{ $ownerableId }}">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
            <span class="d-inline-block bg-primary rounded hr_bar"></span>
            Transfer {{ $entityLabel }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info d-flex align-items-start gap-2">
            <i class="ti tabler-info-circle mt-1"></i>
            <small>
              The {{ strtolower($entityLabel) }} is fully paid, so ownership can be transferred to another
              member (existing or newly created). Only the society transfer fee applies — it becomes the
              incoming owner's transfer-fee plan. The society owner must approve the transfer before
              ownership moves, and the printed transfer serves as the record.
            </small>
          </div>

          <div class="row g-4">
            {{-- Member column --}}
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <h6 class="fw-bold mb-3"><i class="ti tabler-user me-1"></i>Transfer To</h6>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="radio" name="mode" id="tf_mode_existing" value="existing" checked>
                  <label class="form-check-label" for="tf_mode_existing">An existing member</label>
                </div>

                <div id="tf_existing_wrap">
                  <label class="form-label fw-semibold" for="tf_fetch_term">Find by Email or Contact</label>
                  <div class="d-flex gap-2">
                    <input type="text" id="tf_fetch_term" name="fetch_term" class="form-control"
                           placeholder="member@example.com or 0300 1234567" autocomplete="off">
                    <button type="button" class="btn btn-outline-primary" id="tf_fetch_btn" style="white-space:nowrap;">
                      Fetch
                    </button>
                  </div>
                  <input type="hidden" id="tf_existing_user_id" name="existing_user_id" value="">
                  <small class="form-text text-muted">
                    Any existing member of the platform can be picked - they are attached to this society automatically.
                  </small>
                </div>

                <div class="form-check my-3">
                  <input class="form-check-input" type="radio" name="mode" id="tf_mode_new" value="new">
                  <label class="form-check-label" for="tf_mode_new">Create a new member</label>
                </div>

                <div class="form-check my-3">
                  <input class="form-check-input" type="radio" name="mode" id="tf_mode_society" value="return_to_society">
                  <label class="form-check-label" for="tf_mode_society">Return to society</label>
                </div>

                <div id="tf_fetch_status" class="mt-2"></div>

                <div id="tf_new_wrap" style="display:none;">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">First Name</label>
                      <input type="text" name="first_name" class="form-control" placeholder="John">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Last Name</label>
                      <input type="text" name="last_name" class="form-control" placeholder="Doe">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Email</label>
                      <input type="email" name="email" class="form-control" placeholder="john.doe@example.com">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Password</label>
                      <div class="input-group">
                        <input type="password" name="password" id="tf_password" class="form-control"
                               placeholder="············">
                        <button type="button" class="btn btn-outline-secondary" id="tf_toggle_password" tabindex="-1"
                                aria-label="Toggle password visibility">
                          <i class="ti tabler-eye"></i>
                        </button>
                      </div>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="tf_autofill_password">
                        <label class="form-check-label small" for="tf_autofill_password">Auto-fill password</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Country</label>
                      <select name="country" class="form-select">
                        <option value="">Select Country</option>
                        @foreach(['Pakistan','Australia','Bangladesh','Brazil','Canada','China','France','Germany','India','Indonesia','Israel','Italy','Japan','Korea','Mexico','Philippines','Russia','South Africa','Thailand','Turkey','Ukraine','United Arab Emirates','United Kingdom','United States'] as $c)
                          <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Gender</label>
                      <select name="gender" class="form-select">
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">CNIC / Passport</label>
                      <input type="text" name="cnic_passport" class="form-control" placeholder="13503-1235405-3">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Contact</label>
                      <input type="tel" name="contact" class="form-control phone-input" placeholder="0300 1234567">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Date of Birth</label>
                      <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Profile Picture</label>
                      <input type="file" name="picture" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                      <label class="form-label fw-semibold">Profession</label>
                      <input type="text" name="profession" class="form-control" placeholder="Software Engineer">
                    </div>
                    <div class="col-12">
                      <label class="form-label fw-semibold required">Permanent Address</label>
                      <input type="text" name="permanent_address" class="form-control"
                             placeholder="House #123, Street 4, Lahore">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- Transfer fee + remarks column --}}
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <h6 class="fw-bold mb-3"><i class="ti tabler-cash-banknote me-1"></i>Transfer Fee</h6>

                <div class="border rounded-3 p-3 bg-label-secondary mb-3">
                  <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <span class="fw-semibold small text-uppercase text-muted">Society Transfer Fee</span>
                    <span class="badge bg-label-primary">
                      {{ ! $transferQuote['matched'] ? 'Not configured' : ($transferQuote['is_percentage'] ? 'Percentage' : 'Fixed') }}
                    </span>
                  </div>
                  <div class="fw-bold">{{ $transferFeeLabel }}</div>
                  @if($transferQuote['is_percentage'] && $transferQuote['matched'])
                    <small class="text-muted d-block">
                      Base (listed price): {{ number_format($transferQuote['base'], 2) }} {{ $transferCurrency }}
                      — frozen on the transfer at submission.
                    </small>
                  @endif
                </div>

                <div class="mb-1">
                  <label class="form-label fw-semibold" for="transferRemarks">Remarks (optional)</label>
                  <textarea name="remarks" id="transferRemarks" class="form-control" rows="2"
                            placeholder="Anything the society owner should know"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="transferSubmitBtn">
            <i class="ti tabler-send me-1"></i> Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('transferPropertyForm');
      if (!form) return;

      const modal = document.getElementById('transferPropertyModal');
      const modeExisting = document.getElementById('tf_mode_existing');
      const modeNew = document.getElementById('tf_mode_new');
      const modeSociety = document.getElementById('tf_mode_society');
      const existingWrap = document.getElementById('tf_existing_wrap');
      const newWrap = document.getElementById('tf_new_wrap');
      const existingUserId = document.getElementById('tf_existing_user_id');
      const fetchBtn = document.getElementById('tf_fetch_btn');
      const fetchTerm = document.getElementById('tf_fetch_term');
      const fetchStatus = document.getElementById('tf_fetch_status');
      const pwField = document.getElementById('tf_password');
      const pwToggle = document.getElementById('tf_toggle_password');
      const autofillPw = document.getElementById('tf_autofill_password');
      const submitBtn = document.getElementById('transferSubmitBtn');

      const newFieldNames = ['first_name', 'last_name', 'email', 'password', 'contact', 'cnic_passport', 'dob', 'profession', 'permanent_address', 'country', 'gender'];
      const charSet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';

      const getFormField = (name) => form.querySelector(`[name="${name}"]`);
      const setFieldsState = (enabled) => {
        newFieldNames.forEach(nm => {
          const el = getFormField(nm);
          if (el) el.disabled = !enabled;
        });
      };

      const generatePassword = (len) => {
        let out = '';
        const rnd = new Uint32Array(len);
        if (window.crypto?.getRandomValues) window.crypto.getRandomValues(rnd);
        for (let i = 0; i < len; i++) out += charSet[rnd[i] % charSet.length];
        return out;
      };

      const togglePasswordVisibility = () => {
        const isPassword = pwField.type === 'password';
        pwField.type = isPassword ? 'text' : 'password';
        const icon = pwToggle.querySelector('i');
        if (icon) icon.className = isPassword ? 'ti tabler-eye-off' : 'ti tabler-eye';
      };

      const toggleMode = () => {
        const isNew = modeNew.checked;
        const isSociety = modeSociety && modeSociety.checked;
        const isExisting = modeExisting.checked;
        existingWrap.style.display = (isNew || isSociety) ? 'none' : '';
        newWrap.style.display = isNew ? '' : 'none';

        if (fetchTerm) {
          fetchTerm.disabled = isNew || isSociety;
          if (isNew || isSociety) fetchTerm.required = false;
          else fetchTerm.required = true;
        }
        if (isNew || isSociety) existingUserId.value = '';
        setFieldsState(!!isNew);

        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        if (fetchStatus) fetchStatus.innerHTML = '';
      };

      const fetchMember = () => {
        const term = fetchTerm.value.trim();

        if (!term) {
          fetchStatus.className = 'text-danger fst-italic';
          fetchStatus.textContent = 'Empty search field';
          if (existingUserId) existingUserId.value = '';
          return;
        }

        fetchStatus.className = 'text-primary';
        fetchStatus.innerHTML = '<span class="text-muted small">Searching…</span>';

        const url = new URL('{{ route("property_owners.lookup") }}');
        url.searchParams.append('term', term);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
          .then(r => r.json())
          .then(data => {
            if (data.found && data.user) {
              if (existingUserId) existingUserId.value = data.user.id;
              fetchStatus.innerHTML = `<span class="text-success small"><i class="ti tabler-user-check"></i> Already exists: <strong>${data.user.first_name || ''} ${data.user.last_name || ''}</strong> — ${data.user.email || data.user.contact}</span>` +
                '&nbsp;<span class="badge bg-label-primary"><i class="ti tabler-user-check me-1"></i>Existing Member</span>';
              if (modeExisting) modeExisting.checked = true;
              if (modeNew) modeNew.checked = false;
              if (modeSociety) modeSociety.checked = false;
              toggleMode();
            } else {
              if (existingUserId) existingUserId.value = '';
              if (modeNew) modeNew.checked = true;
              if (modeExisting) modeExisting.checked = false;
              if (modeSociety) modeSociety.checked = false;
              toggleMode();
              fetchStatus.innerHTML = '<span class="text-warning small"><i class="ti tabler-alert-triangle"></i> Not exists. Create a new member below.</span>';
            }
          })
          .catch(() => {
            fetchStatus.innerHTML = '<span class="text-danger small">Lookup failed. Try again.</span>';
          });
      };

      const resetModal = () => {
        if (existingUserId) existingUserId.value = '';
        if (fetchTerm) {
          fetchTerm.value = '';
          fetchTerm.disabled = false;
          fetchTerm.required = true;
        }
        if (fetchStatus) fetchStatus.innerHTML = '';
        if (autofillPw) autofillPw.checked = false;
        if (pwField) {
          pwField.readOnly = false;
          pwField.type = 'password';
          pwField.value = '';
        }
        if (pwToggle) {
          const icon = pwToggle.querySelector('i');
          if (icon) icon.className = 'ti tabler-eye';
        }

        newFieldNames.forEach(nm => {
          const el = getFormField(nm);
          if (el && nm !== 'picture') el.value = '';
        });

        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

        if (modeExisting) modeExisting.checked = true;
        if (modeNew) modeNew.checked = false;
        if (modeSociety) modeSociety.checked = false;
        if (typeof toggleMode === 'function') toggleMode();
      };

      modal.addEventListener('show.bs.modal', resetModal);
      if (modeExisting) modeExisting.addEventListener('change', toggleMode);
      if (modeNew) modeNew.addEventListener('change', toggleMode);
      if (modeSociety) modeSociety.addEventListener('change', toggleMode);

      if (fetchBtn) fetchBtn.addEventListener('click', fetchMember);
      if (fetchTerm) {
        fetchTerm.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            fetchMember();
          }
        });
        fetchTerm.addEventListener('input', () => {
          if (existingUserId) existingUserId.value = '';
        });
      }

      if (pwToggle) pwToggle.addEventListener('click', togglePasswordVisibility);
      if (autofillPw) {
        autofillPw.addEventListener('change', () => {
          if (!pwField) return;
          if (autofillPw.checked) {
            pwField.value = generatePassword(10);
            pwField.readOnly = true;
          } else {
            pwField.value = '';
            pwField.readOnly = false;
          }
        });
      }

      const setSubmitting = (submitting) => {
        if (!submitBtn) return;
        submitBtn.disabled = submitting;
        if (submitting) {
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting…';
        } else {
          submitBtn.innerHTML = '<i class="ti tabler-send me-1"></i> Submit Request';
        }
      };

      if (submitBtn) {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          e.stopPropagation();

          if (modeSociety && modeSociety.checked) {
            if (existingUserId) existingUserId.value = '';
            if (fetchTerm) { fetchTerm.disabled = true; fetchTerm.required = false; }
            setFieldsState(false);
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
          } else if (modeNew && modeNew.checked) {
            if (fetchTerm) { fetchTerm.disabled = true; fetchTerm.required = false; }
            if (existingUserId) existingUserId.value = '';
          } else if (modeExisting && modeExisting.checked) {
            if (fetchTerm) { fetchTerm.disabled = false; fetchTerm.required = true; }
            if (existingUserId && !existingUserId.value) {
              if (fetchStatus) {
                fetchStatus.className = 'text-danger fst-italic';
                fetchStatus.textContent = 'Fetch and select the member first, or switch to creating a new member.';
              }
              return;
            }
          }

          if (submitBtn) setSubmitting(true);
          const formData = new FormData(form);
          const tokenInput = form.querySelector('input[name="_token"]');
          if (tokenInput) formData.set('_token', tokenInput.value);

          fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
          })
            .then(async (res) => {
              const data = await res.json().catch(() => ({}));

              if (!res.ok) {
                const fieldErrors = data.errors || {};
                Object.keys(fieldErrors).forEach((field) => {
                  const input = form.querySelector(`[name="${field}"]`);
                  if (input) {
                    input.classList.add('is-invalid');
                    input.insertAdjacentHTML('afterend',
                      '<div class="invalid-feedback d-block">' + (fieldErrors[field][0] || 'Invalid value') + '</div>');
                  }
                });
                if (submitBtn) setSubmitting(false);
                return;
              }

              if (typeof notify === 'function') notify(data.message || 'Transfer submitted.', 'success');
              bootstrap.Modal.getInstance(modal)?.hide();
              setTimeout(() => window.location.reload(), 900);
            })
            .catch(() => {
              if (submitBtn) setSubmitting(false);
            });
        });
      }
      if (typeof toggleMode === 'function') toggleMode();
    });
  </script>
@endpush
