/**
 * Installment plan calculator — plain-JS mirror of the shared PHP
 * source of truth (app/Support/InstallmentPlanCalculator.php).
 *
 * Keep the formulas here in sync with the PHP class:
 *   - installments per year: monthly=12, quarterly=4, biannually=2, annually=1
 *   - balloons per year:     quarterly=4, biannually=2, annually=1
 *   - balloon count = (years x balloonsPerYear) - 1  (final event is a normal installment)
 *   - balloon step = installmentsPerYear / balloonsPerYear
 *   - balloon base (deferred) = gross amount - included charges
 *   - total balloon = balloon base x percentage (or a fixed amount)
 *   - per balloon    = ceil(total balloon / balloon count)
 *   - per installment = ceil((balloon base - total balloon) / normal count)
 *
 * Amount breakdown exposed to every caller (kept in sync everywhere):
 *   - gross amount                 = listing price + additional charges + extra installments
 *   - remaining amount             = gross amount - included charges
 *   - additional charges           = excluded charges
 *   - extra installments           = additional amount
 *   - total balloon                = balloon base x percentage (or a fixed amount)
 *   - installment remaining        = balloon base - total balloon
 *   - final amount                 = balloon base + included charges (= plan base)
 */
(function() {
  'use strict';

  const INSTALLMENTS_PER_YEAR = {
    monthly: 12,
    quarterly: 4,
    biannually: 2,
    annually: 1
  };

  const BALLOONS_PER_YEAR = {
    quarterly: 4,
    biannually: 2,
    annually: 1
  };

  const FREQUENCY_RANK = {
    monthly: 1,
    quarterly: 2,
    biannually: 3,
    annually: 4
  };

  function installmentsPerYear(frequency) {
    return INSTALLMENTS_PER_YEAR[frequency] || 12;
  }

  function balloonsPerYear(frequency) {
    return BALLOONS_PER_YEAR[frequency] || 4;
  }

  function balloonFrequencyValid(installmentFrequency, balloonFrequency) {
    if (!installmentFrequency || !balloonFrequency) return false;
    return (FREQUENCY_RANK[balloonFrequency] || 0) > (FREQUENCY_RANK[installmentFrequency] || 0);
  }

  function balloonCount(numberOfInstallments, installmentFrequency, balloonFrequency) {
    const years = numberOfInstallments / installmentsPerYear(installmentFrequency);
    return Math.max(0, Math.ceil(years * balloonsPerYear(balloonFrequency)) - 1);
  }

  function toFloat(value) {
    const n = parseFloat(value);
    return Number.isNaN(n) ? 0 : n;
  }

  function calculate(input) {
    const listingPrice = toFloat(input.listing_price);
    const includedTotal = toFloat(input.included_charges_total);
    const excludedTotal = toFloat(input.excluded_charges_total);
    const additional = toFloat(input.additional_amount);
    const num = parseInt(input.number_of_installments, 10) || 0;
    const installmentFrequency = input.installment_frequency || 'monthly';
    const balloonFrequency = input.balloon_payment_frequency || null;

    const balloonRaw = toFloat(input.balloon_payment);
    const balloonPer = Boolean(input.balloon_payment_in_per);

    const ipp = installmentsPerYear(installmentFrequency);
    const bpy = balloonFrequency ? balloonsPerYear(balloonFrequency) : 0;
    const years = num > 0 ? num / ipp : 0;

    const grossAmount = listingPrice + excludedTotal + additional;
    const remainingAmount = grossAmount - includedTotal;
    const additionalCharges = excludedTotal;
    const extraInstallments = additional;

    const balloonBase = grossAmount - includedTotal;

    const errors = [];
    const hasBalloon = balloonRaw > 0;

    if (hasBalloon) {
      if (!balloonFrequency) {
        errors.push('Balloon frequency is required when balloon payment is set');
      } else if (!balloonFrequencyValid(installmentFrequency, balloonFrequency)) {
        errors.push('Balloon frequency must be less frequent than the installment frequency');
      }
    }

    const totalBalloon = balloonPer ? balloonBase * balloonRaw / 100 : balloonRaw;

    let balloonCnt, normalCount, step, perBalloon, perInstallment;

    if (hasBalloon && errors.length === 0) {
      balloonCnt = balloonCount(num, installmentFrequency, balloonFrequency);
      normalCount = Math.max(0, num - balloonCnt);
      step = bpy > 0 ? Math.floor(ipp / bpy) : 0;
      perBalloon = balloonCnt > 0 ? Math.ceil(totalBalloon / balloonCnt) : 0;
      perInstallment = normalCount > 0 ? Math.ceil((balloonBase - totalBalloon) / normalCount) : 0;
    } else {
      balloonCnt = 0;
      normalCount = num;
      step = 0;
      perBalloon = 0;
      perInstallment = num > 0 ? Math.ceil(balloonBase / num) : 0;
    }

    const schedule = [];
    let cumulative = 0;
    let balloonIndex = 0;
    let installmentNo = 0;

    for (let i = 1; i <= num; i++) {
      const isBalloon = balloonCnt > 0 && step > 0 && i % step === 0 && balloonIndex < balloonCnt;

      if (isBalloon) {
        balloonIndex++;
        cumulative += perBalloon;
        schedule.push({ no: i, type: 'balloon', balloon_no: balloonIndex, amount: perBalloon, cumulative: cumulative });
      } else {
        installmentNo++;
        cumulative += perInstallment;
        schedule.push({ no: installmentNo, type: 'installment', balloon_no: null, amount: perInstallment, cumulative: cumulative });
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors,
      installments_per_year: ipp,
      balloons_per_year: bpy,
      total_years: years,
      step: step,
      gross_amount: grossAmount,
      plan_base: grossAmount,
      balloon_base: balloonBase,
      remaining_amount: remainingAmount,
      additional_charges: additionalCharges,
      extra_installments: extraInstallments,
      total_balloon: totalBalloon,
      installment_remaining: balloonBase - totalBalloon,
      final_amount: balloonBase + includedTotal,
      balloon_count: balloonCnt,
      normal_count: normalCount,
      per_balloon: perBalloon,
      per_installment: perInstallment,
      plan_total: grossAmount,
      schedule_total: cumulative,
      schedule: schedule
    };
  }

  function cap(value) {
    if (!value) return value;
    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  window.InstallmentPlanCalculator = {
    INSTALLMENTS_PER_YEAR: INSTALLMENTS_PER_YEAR,
    BALLOONS_PER_YEAR: BALLOONS_PER_YEAR,
    FREQUENCY_RANK: FREQUENCY_RANK,
    installmentsPerYear: installmentsPerYear,
    balloonsPerYear: balloonsPerYear,
    balloonFrequencyValid: balloonFrequencyValid,
    balloonCount: balloonCount,
    calculate: calculate
  };
})();
