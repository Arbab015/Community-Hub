@php
  $propertyPlan = $paymentPlan ?? null;
  $installs = collect($planInstallments ?? collect());
  $installLabel = fn ($att) => (($att->name ?? '') ?: ($att->installmentTemplate?->name ?: 'Installment Plan'))
      . ($att->number_of_installments ? ' ('.$att->number_of_installments.' installments)' : '');
@endphp

<div class="modal fade" id="createMemberOwnerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="{{ route('property_owners.store') }}" enctype="multipart/form-data" id="form">
        @csrf
        <input type="hidden" name="ownerable_type" id="ownerable_type" value="{{ $ownerableType ?? '' }}">
        <input type="hidden" name="ownerable_id" id="ownerable_id" value="{{ $ownerableId ?? '' }}">
        <input type="hidden" name="society" id="society" value="{{ $propertySocietyId ?? '' }}">
        @if($propertyPlan)
          <input type="hidden" name="payment_plan_id" value="{{ $propertyPlan->id }}">
        @endif

        <div class="modal-header">
          <h5 class="modal-title">Assign Owner</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="text-muted small mb-3">
            Link an existing member or create a new one, then set them as the current owner. The property's own payment
            plan is used.
          </p>

          <div class="row g-4">
            {{-- Member column --}}
            <div class="col-md-6" id="member_col">
              <div class="border rounded-3 p-3 h-100">
                <h6 class="fw-bold mb-3"><i class="ti tabler-user me-1"></i>Member</h6>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="radio" name="mode" id="mode_existing" value="existing" checked>
                  <label class="form-check-label" for="mode_existing">Use an existing member</label>
                </div>

                <div id="existing_wrap">
                  <label class="form-label fw-semibold" for="fetch_term">Find by Email or Contact</label>
                  <div class="d-flex gap-2">
                    <input type="text" id="fetch_term" name="fetch_term" class="form-control"
                           placeholder="member@example.com or 0300 1234567" autocomplete="off" required>
                    <button type="button" class="btn btn-outline-primary" id="fetch_btn" style="white-space:nowrap;">
                      Fetch
                    </button>
                  </div>
                  <input type="hidden" id="existing_user_id" name="existing_user_id" value="">
                </div>

                <div class="form-check my-3">
                  <input class="form-check-input" type="radio" name="mode" id="mode_new" value="new">
                  <label class="form-check-label" for="mode_new">Create a new member</label>
                </div>

                <div id="fetch_status" class="mt-2"></div>

                <div id="new_wrap" style="display:none;">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">First Name</label>
                      <input type="text" name="first_name" class="form-control" placeholder="John" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Last Name</label>
                      <input type="text" name="last_name" class="form-control" placeholder="Doe">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Email</label>
                      <input type="email" name="email" class="form-control" placeholder="john.doe@example.com" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Password</label>
                      <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="············" required>
                        <button type="button" class="btn btn-outline-secondary" id="toggle_password" tabindex="-1"
                                aria-label="Toggle password visibility">
                          <i class="ti tabler-eye"></i>
                        </button>
                      </div>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="autofill_password">
                        <label class="form-check-label small" for="autofill_password">Auto-fill password</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Country</label>
                      <select name="country" class="form-select" required>
                        <option value="">Select Country</option>
                        @foreach(['Pakistan','Australia','Bangladesh','Brazil','Canada','China','France','Germany','India','Indonesia','Israel','Italy','Japan','Korea','Mexico','Philippines','Russia','South Africa','Thailand','Turkey','Ukraine','United Arab Emirates','United Kingdom','United States'] as $c)
                          <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Gender</label>
                      <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">CNIC / Passport</label>
                      <input type="text" name="cnic_passport" class="form-control" placeholder="13503-1235405-3"
                             required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold required">Contact</label>
                      <input type="tel" name="contact" class="form-control phone-input" placeholder="0300 1234567"
                             required>
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
                      <input type="text" name="permanent_address" class="form-control" placeholder="House #123, Street 4, Lahore" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- Payment plan column --}}
            <div class="col-md-6" id="plan_col">
              <div class="border rounded-3 p-3 h-100">
                <div id="member_info" class="alert alert-success d-flex align-items-center gap-2 mb-3 d-none">
                  <i class="ti tabler-user-check"></i>
                  <span id="member_info_text"><b>Linking existing society member.</b></span>
                </div>

                <h6 class="fw-bold mb-3"><i class="ti tabler-file-invoice me-1"></i>Payment Plan</h6>

                @if($propertyPlan)
                  <div class="alert alert-primary d-flex align-items-center gap-2 mb-3">
                    <i class="ti tabler-circle-check-filled"></i>
                    <span>
                      <b>{{ $propertyPlan->paymentTemplate?->name ?? 'Custom Plan' }}</b>
                      @if($propertyPlan->paymentTemplate)
                        <span class="badge bg-label-secondary ms-1">Template Linked</span>
                      @else
                        <span class="badge bg-label-secondary ms-1">Custom</span>
                      @endif
                    </span>
                  </div>
                  <div class="text-muted small mb-2">This property's existing payment plan will be used when the owner
                    is assigned. Other plans for this property/unit are removed.
                  </div>
                @else
                  <div class="alert alert-warning mb-3"><i class="ti tabler-alert-triangle me-1"></i>No payment plan is
                    set for this property/unit yet.
                  </div>
                @endif

                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" id="with_installment" name="with_installment"
                         value="1">
                  <label class="form-check-label" for="with_installment">Include an installment plan</label>
                </div>

                <div id="installment_wrap" style="display:none;">
                  @if($installs->count())
                    <label class="form-label fw-semibold">Select one Installment Plan</label>
                    <select name="installment_attachment_id" id="installment_select" class="form-select">
                      <option value="">Choose an installment plan</option>
                      @foreach($installs as $att)
                        <option value="{{ $att->id }}">
                          {{ $installLabel($att) }}
                          @if(! $att->installment_template_id)
                            (custom)
                          @endif
                        </option>
                      @endforeach
                    </select>
                    <div class="text-muted small mt-1">Only one installment plan can be selected.</div>
                  @else
                    <div class="text-muted small">This property's plan has no installment plans attached.</div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ti tabler-user-check me-1"></i>Assign Owner</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // DOM Elements
      const form = document.getElementById('form');
      const existingUserId = document.getElementById('existing_user_id');
      const modeExisting = document.getElementById('mode_existing');
      const modeNew = document.getElementById('mode_new');
      const existingWrap = document.getElementById('existing_wrap');
      const newWrap = document.getElementById('new_wrap');
      const withInstallment = document.getElementById('with_installment');
      const installmentWrap = document.getElementById('installment_wrap');
      const fetchTerm = document.getElementById('fetch_term');
      const fetchBtn = document.getElementById('fetch_btn');
      const fetchStatus = document.getElementById('fetch_status');
      const pwField = document.getElementById('password');
      const pwToggle = document.getElementById('toggle_password');
      const autofillPw = document.getElementById('autofill_password');
      const modal = document.getElementById('createMemberOwnerModal');
      const memberCol = document.getElementById('member_col');
      const planCol = document.getElementById('plan_col');
      const memberInfo = document.getElementById('member_info');
      const memberInfoText = document.getElementById('member_info_text');
      const modalTitle = modal.querySelector('.modal-title');

      const newFieldNames = ['first_name', 'last_name', 'email', 'password', 'contact', 'cnic_passport', 'dob', 'profession', 'permanent_address', 'country', 'gender'];
      const charSet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';

      // Utility Functions
      const getFormField = (name) => form.querySelector(`[name="${name}"]`);
      const setFieldsState = (enabled) => newFieldNames.forEach(nm => {
        const el = getFormField(nm);
        if (el) el.disabled = !enabled;
      });

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

      const showFetchStatus = (found, user = null, alreadyMember = false) => {
        if (found && user) {
          existingUserId.value = user.id;
          fetchStatus.innerHTML = `<span class="text-success small"><i class="ti tabler-user-check"></i> Already exists: <strong>${user.first_name || ''} ${user.last_name || ''}</strong> — ${user.email || user.contact}</span>` +
            (alreadyMember ? '&nbsp;<span class="badge bg-label-primary"><i class="ti tabler-user-check me-1"></i>Already Society Member</span>' : '');
        } else {
          existingUserId.value = '';
          modeNew.checked = true;
          modeExisting.checked = false;
          toggleMode();
          fetchStatus.innerHTML = '<span class="text-warning small"><i class="ti tabler-alert-triangle"></i> Not exists. Create a new member below.</span>';
        }
      };

      // Mode Toggle
      const toggleMode = () => {
        const isNew = modeNew.checked;
        existingWrap.style.display = isNew ? 'none' : '';
        newWrap.style.display = isNew ? '' : 'none';
        fetchTerm.required = !isNew;
        if (isNew) existingUserId.value = '';
        setFieldsState(isNew);
      };

      // Fetch Member
      const fetchMember = () => {
        const term = fetchTerm.value.trim();
        if (!term) {
          fetchStatus.innerHTML = '';
          existingUserId.value = '';
          return;
        }

        fetchStatus.innerHTML = '<span class="text-muted small">Searching…</span>';
        const url = new URL('{{ route("property_owners.lookup") }}', window.location.origin);
        url.searchParams.append('term', term);
        if (document.getElementById('society').value) {
          url.searchParams.append('society', document.getElementById('society').value);
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(r => r.json())
          .then(data => showFetchStatus(data.found, data.user, data.already_member))
          .catch(() => fetchStatus.innerHTML = '<span class="text-danger small">Lookup failed. Try again.</span>');
      };

      // Member Section
      const setMemberSection = (preselectId, label) => {
        if (preselectId) {
          existingUserId.value = preselectId;
          memberCol.style.display = 'none';
          planCol.classList.remove('col-md-6');
          planCol.classList.add('col-md-12');
          memberInfo.classList.remove('d-none');
          if (label) memberInfoText.textContent = label;
        } else {
          existingUserId.value = '';
          memberCol.style.display = '';
          planCol.classList.remove('col-md-12');
          planCol.classList.add('col-md-6');
          memberInfo.classList.add('d-none');
        }
      };

      // Modal States
      const applyCreateOnlyMode = () => {
        modeExisting.closest('.form-check').style.display = 'none';
        existingWrap.style.display = 'none';
        modeExisting.checked = false;
        modeNew.checked = true;
        newWrap.style.display = '';
        modalTitle.textContent = 'Create Member';
      };

      const applyFullMode = () => {
        modeExisting.closest('.form-check').style.display = '';
        existingWrap.style.display = '';
        newWrap.style.display = 'none';
        modalTitle.textContent = 'Assign Owner';
      };

      // Prefill New Member
      const prefillNewMember = (btn) => {
        if (!btn) return;
        const name = (btn.dataset.prefillName || '').trim().split(/\s+/).filter(Boolean);
        const firstName = name.shift() || '';
        const lastName = name.join(' ') || '';
        const email = btn.dataset.prefillEmail || '';
        const contact = btn.dataset.prefillContact || '';

        const fnEl = getFormField('first_name');
        const lnEl = getFormField('last_name');
        const emEl = getFormField('email');
        const ctEl = getFormField('contact');

        if (fnEl) fnEl.value = firstName;
        if (lnEl) lnEl.value = lastName;
        if (emEl) emEl.value = email;
        if (ctEl) ctEl.value = contact;
      };

      // Reset Modal
      const resetModal = () => {
        existingUserId.value = '';
        fetchTerm.value = '';
        fetchStatus.innerHTML = '';
        autofillPw.checked = false;
        pwField.readOnly = false;
        pwField.type = 'password';
        const icon = pwToggle.querySelector('i');
        if (icon) icon.className = 'ti tabler-eye';

        newFieldNames.forEach(nm => {
          const el = getFormField(nm);
          if (el && nm !== 'picture') el.value = '';
        });

        memberInfo.classList.add('d-none');
      };

      // Open Modal
      const openModal = (btn, isCreate) => {
        document.getElementById('ownerable_type').value = btn.dataset.ownerableType || '';
        document.getElementById('ownerable_id').value = btn.dataset.ownerableId || '';
        resetModal();

        const memberId = btn.dataset.memberId || null;
        const memberLabel = btn.dataset.memberLabel || null;

        if (memberId) {
          applyFullMode();
          setMemberSection(memberId, memberLabel);
          modeNew.checked = false;
          modeExisting.checked = false;
          setFieldsState(false);
          fetchTerm.required = false;
          existingWrap.style.display = 'none';
          newWrap.style.display = 'none';
        } else if (isCreate) {
          applyCreateOnlyMode();
          setMemberSection(null, null);
          prefillNewMember(btn);
          toggleMode();
        } else {
          applyFullMode();
          setMemberSection(null, null);
          modeNew.checked = false;
          modeExisting.checked = true;
          toggleMode();
        }

        bootstrap.Modal.getOrCreateInstance(modal).show();
      };

      // Event Listeners
      modeExisting.addEventListener('change', toggleMode);
      modeNew.addEventListener('change', toggleMode);
      withInstallment.addEventListener('change', () => {
        installmentWrap.style.display = withInstallment.checked ? '' : 'none';
      });

      fetchBtn.addEventListener('click', fetchMember);
      fetchTerm.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          fetchMember();
        }
      });

      autofillPw.addEventListener('change', () => {
        if (autofillPw.checked) {
          pwField.value = generatePassword(10);
          pwField.readOnly = true;
        } else {
          pwField.value = '';
          pwField.readOnly = false;
        }
      });

      pwToggle.addEventListener('click', togglePasswordVisibility);

      document.addEventListener('click', (e) => {
        const createBtn = e.target.closest('.create-member-btn');
        const assignBtn = e.target.closest('.assign-member-btn');
        if (createBtn) openModal(createBtn, true);
        else if (assignBtn) openModal(assignBtn, false);
      });

      // Initialize
      toggleMode();
    });
  </script>
@endpush
